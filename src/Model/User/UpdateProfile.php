<?php

namespace App\Model\User;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateProfile
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    public string $firstName;

    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    public string $lastName;

    #[Assert\NotBlank]
    #[Assert\Length(min: 6, max: 40)]
    public string $phone;

    public bool $marketingConsent = false;
}
