<?php

namespace App\Provider\Shopping;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Shopping\StoreOrder;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<StoreOrder>
 */
class MyStoreOrdersProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        return $this->em->getRepository(StoreOrder::class)
            ->createQueryBuilder('so')
            ->join('so.storeBox', 'sb')
            ->where('sb.owner = :user')
            ->setParameter('user', $user)
            ->orderBy('so.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
