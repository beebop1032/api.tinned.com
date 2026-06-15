<?php

namespace App\Model\User;

use Symfony\Component\Validator\Constraints as Assert;

class ResetPassword
{
    #[Assert\NotBlank]
    public string $token;

    #[Assert\NotBlank]
    #[Assert\Length(min: 8)]
    public string $password;
}
