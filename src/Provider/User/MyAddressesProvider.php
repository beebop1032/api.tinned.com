<?php

namespace App\Provider\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Entity\User\Address;
use App\Model\User\AddressResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

readonly class MyAddressesProvider implements ProviderInterface
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

        $addresses = $this->em->getRepository(Address::class)->findBy(['user' => $user], ['id' => 'DESC']);

        return array_map(static fn (Address $address) => AddressResponse::fromAddress($address), $addresses);
    }
}
