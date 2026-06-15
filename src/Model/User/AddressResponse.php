<?php

namespace App\Model\User;

use App\Entity\User\Address;

class AddressResponse
{
    public function __construct(
        public int $id,
        public string $firstName,
        public string $lastName,
        public string $street,
        public string $postalCode,
        public string $city,
        public string $countryCode,
        public ?string $phone,
    ) {}

    public static function fromAddress(Address $address): self
    {
        return new self(
            $address->getId() ?? 0,
            $address->getFirstName(),
            $address->getLastName(),
            $address->getStreet(),
            $address->getPostalCode(),
            $address->getCity(),
            $address->getCountryCode(),
            $address->getPhone(),
        );
    }
}
