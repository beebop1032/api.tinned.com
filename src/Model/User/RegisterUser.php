<?php

namespace App\Model\User;

use Symfony\Component\Validator\Constraints as Assert;


class RegisterUser
{
    #[Assert\NotBlank]
    #[Assert\NotNull]
    #[Assert\Email]
    public string $email;

    #[Assert\NotBlank]
    #[Assert\NotNull]
    #[Assert\Length(min: 8)]
    public string $password;

    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    public string $firstName;

    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    public string $lastName;

    #[Assert\NotBlank]
    #[Assert\Length(min: 6, max: 40)]
    public string $phone;

    #[Assert\IsTrue(message: 'Vous devez accepter les conditions générales et la politique de traitement des données.')]
    public bool $acceptedTerms = false;

    public bool $marketingConsent = false;
}
