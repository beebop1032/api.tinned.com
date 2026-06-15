<?php

namespace App\Model\User;

use Symfony\Component\Validator\Constraints as Assert;

class AddressInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    public string $firstName;

    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    public string $lastName;

    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    public string $street;

    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    public string $postalCode;

    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    public string $city;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[A-Za-z]{2}$/', message: 'Le pays doit être indiqué avec son code à deux lettres.')]
    public string $countryCode = 'BE';

    #[Assert\Length(max: 40)]
    public ?string $phone = null;
}
