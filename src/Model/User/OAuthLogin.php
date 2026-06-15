<?php

namespace App\Model\User;

use Symfony\Component\Validator\Constraints as Assert;

class OAuthLogin
{
    #[Assert\NotBlank]
    public string $provider;

    #[Assert\NotBlank]
    public string $token;
}
