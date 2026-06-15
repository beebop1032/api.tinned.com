<?php

namespace App\Provider\Shopping;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Shopping\CustomerOrder;
use App\Entity\User;
use App\Factory\Shopping\CheckoutResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

readonly class MyOrdersProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private CheckoutResponseFactory $responseFactory,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        $orders = $this->em->getRepository(CustomerOrder::class)->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC'],
            20,
        );

        return array_map(fn (CustomerOrder $order) => $this->responseFactory->fromOrder($order), $orders);
    }
}
