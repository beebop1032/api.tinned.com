<?php

namespace App\Model\User;

class RegisterUserResponse
{
    public int $id;
    public string $email;
    public string $token;
    public string $firstName;
    public string $lastName;
    public string $phone;

    public function __construct(int $id, string $email, string $token, string $firstName, string $lastName, string $phone)
    {
        $this->id = $id;
        $this->email = $email;
        $this->token = $token;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->phone = $phone;
    }
}
