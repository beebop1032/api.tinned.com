<?php

namespace App\Service\Shipping;

final readonly class ShippingSender
{
    public function __construct(
        public string $name,
        public string $street,
        public string $streetNumber,
        public string $postalCode,
        public string $city,
        public string $countryCode,
        public string $phone,
        public string $email,
    ) {
    }

    public function requireConfigured(): void
    {
        foreach (['street', 'postalCode', 'city', 'phone', 'email'] as $field) {
            if (trim($this->{$field}) === '') {
                throw new ShippingLabelException('Les coordonnees expediteur Tinned ne sont pas configurees.');
            }
        }
    }
}
