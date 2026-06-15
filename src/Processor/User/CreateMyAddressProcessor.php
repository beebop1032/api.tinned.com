<?php

namespace App\Processor\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Entity\User\Address;
use App\Model\User\AddressInput;
use App\Model\User\AddressResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

readonly class CreateMyAddressProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AddressResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || !$data instanceof AddressInput) {
            throw new \LogicException('Authenticated user required.');
        }

        $address = (new Address())
            ->setUser($user)
            ->setFirstName(trim($data->firstName))
            ->setLastName(trim($data->lastName))
            ->setStreet(trim($data->street))
            ->setPostalCode(trim($data->postalCode))
            ->setCity(trim($data->city))
            ->setCountryCode(strtoupper(trim($data->countryCode)))
            ->setPhone($data->phone !== null ? trim($data->phone) : null);

        $this->em->persist($address);
        $this->em->flush();

        return AddressResponse::fromAddress($address);
    }
}
