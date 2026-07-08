<?php

namespace App\Processor\Review;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Review\Review;
use App\Entity\Shopping\CustomerOrder;
use App\Entity\Shopping\OrderLine;
use App\Entity\User;
use App\Service\Review\ProductRatingUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Handles a customer submitting a review. The author is taken from the authenticated
 * user (never the payload), the "verified purchase" badge is computed from paid orders,
 * and the review starts as pending until an admin approves it.
 */
class ReviewProcessor implements ProcessorInterface
{
    private const PURCHASED_STATUSES = [
        CustomerOrder::STATUS_PAID,
        CustomerOrder::STATUS_PROCESSING,
        CustomerOrder::STATUS_SHIPPED,
        CustomerOrder::STATUS_COMPLETED,
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly ProductRatingUpdater $ratingUpdater,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Review
    {
        if (!$data instanceof Review) {
            throw new \InvalidArgumentException('Expected a Review.');
        }

        $user = $this->security->getUser();
        if ($user instanceof User) {
            $data->setUser($user);
            if (trim($data->getAuthorName()) === '') {
                $data->setAuthorName($this->displayName($user));
            }
            $data->setVerifiedPurchase($this->hasPurchased($user, $data));
        }

        // A moderator, not the author, decides visibility.
        $data->setStatus(Review::STATUS_PENDING);
        $data->setMerchantResponse(null);

        $this->em->persist($data);
        $this->em->flush();

        // Pending reviews don't count yet, but recompute defensively (idempotent).
        if ($data->getProduct() !== null) {
            $this->ratingUpdater->recompute($data->getProduct());
        }

        return $data;
    }

    private function displayName(User $user): string
    {
        $name = trim(sprintf('%s %s', $user->getFirstName() ?? '', $user->getLastName() ?? ''));
        if ($name !== '') {
            return mb_substr($name, 0, 80);
        }

        // Fall back to the email local part rather than exposing the full address.
        $email = (string) $user->getEmail();
        return mb_substr(strstr($email, '@', true) ?: 'Client', 0, 80);
    }

    private function hasPurchased(User $user, Review $review): bool
    {
        if ($review->getProduct() === null) {
            return false;
        }

        $count = (int) $this->em->getRepository(OrderLine::class)
            ->createQueryBuilder('ol')
            ->select('COUNT(ol.id)')
            ->join('ol.customerOrder', 'co')
            ->join('ol.variant', 'v')
            ->where('co.user = :user')
            ->andWhere('v.product = :product')
            ->andWhere('co.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('product', $review->getProduct())
            ->setParameter('statuses', self::PURCHASED_STATUSES)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
