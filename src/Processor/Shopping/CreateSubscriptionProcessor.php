<?php

namespace App\Processor\Shopping;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Shopping\BoxSubscription;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Creates a box subscription for the current user: attaches the buyer, derives the
 * store box from the chosen variant, and schedules the first renewal. The Mollie
 * mandate/subscription are left null until recurring billing is enabled.
 *
 * @implements ProcessorInterface<BoxSubscription>
 */
readonly class CreateSubscriptionProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BoxSubscription
    {
        if (!$data instanceof BoxSubscription) {
            throw new \InvalidArgumentException('Expected a BoxSubscription.');
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }

        $data->setUser($user);
        $data->setStoreBox($data->getVariant()?->getProduct()?->getStoreBox());
        $data->setStatus(BoxSubscription::STATUS_ACTIVE);
        $data->setNextRenewalAt($data->computeNextRenewal(new \DateTimeImmutable()));

        $this->em->persist($data);
        $this->em->flush();

        return $data;
    }
}
