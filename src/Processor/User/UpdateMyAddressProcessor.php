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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

readonly class UpdateMyAddressProcessor implements ProcessorInterface
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

        $address = $this->em->getRepository(Address::class)->findOneBy([
            'id' => (int) ($uriVariables['id'] ?? 0),
            'user' => $user,
        ]);
        if (!$address instanceof Address) {
            throw new NotFoundHttpException('Adresse introuvable.');
        }

        $address
            ->setFirstName(trim($data->firstName))
            ->setLastName(trim($data->lastName))
            ->setStreet(trim($data->street))
            ->setPostalCode(trim($data->postalCode))
            ->setCity(trim($data->city))
            ->setCountryCode(strtoupper(trim($data->countryCode)))
            ->setPhone($data->phone !== null ? trim($data->phone) : null);

        $this->em->flush();

        return AddressResponse::fromAddress($address);
    }
}
