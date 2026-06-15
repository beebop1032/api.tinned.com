<?php

namespace App\Model\User;

class PasswordResetResponse
{
    public function __construct(public string $message)
    {
    }
}
