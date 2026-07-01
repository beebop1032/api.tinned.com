<?php

namespace App\Provider\Shopping;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Shopping\BoxSubscription;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<BoxSubscription>
 */
class MySubscriptionsProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): BoxSubscription|array|null
    {
        $user = $this->security->getUser();

        if (isset($uriVariables['id'])) {
            if (!$user instanceof User) {
                return null;
            }
            $subscription = $this->em->getRepository(BoxSubscription::class)->find($uriVariables['id']);

            return $subscription && $subscription->getUser()?->getId() === $user->getId() ? $subscription : null;
        }

        if (!$user instanceof User) {
            return [];
        }

        return $this->em->getRepository(BoxSubscription::class)
            ->createQueryBuilder('s')
            ->where('s.user = :user')
            ->setParameter('user', $user)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
