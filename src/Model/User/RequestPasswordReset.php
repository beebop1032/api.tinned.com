<?php

namespace App\Model\User;

use Symfony\Component\Validator\Constraints as Assert;

class RequestPasswordReset
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email;
}
