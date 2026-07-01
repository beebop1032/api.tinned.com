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

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): StoreOrder|array|null
    {
        $user = $this->security->getUser();

        // Item operation (Patch): return the single store order, owner-scoped.
        if (isset($uriVariables['id'])) {
            if (!$user instanceof User) {
                return null;
            }
            $storeOrder = $this->em->getRepository(StoreOrder::class)->find($uriVariables['id']);
            if (!$storeOrder || $storeOrder->getStoreBox()?->getOwner()?->getId() !== $user->getId()) {
                return null;
            }

            return $storeOrder;
        }

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
