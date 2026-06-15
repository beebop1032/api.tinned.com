<?php

namespace App\Model\User;

class MeResponse
{
    public bool $logged;

    public ?string $email;
    public ?bool $active;
    public ?string $firstName;
    public ?string $lastName;
    public ?string $phone;
    public ?bool $marketingConsent;

    public function __construct(
        bool $logged,
        ?string $email,
        ?bool $active = null,
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $phone = null,
        ?bool $marketingConsent = null,
    )
    {
        $this->logged = $logged;
        $this->email = $email;
        $this->active = $active;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->phone = $phone;
        $this->marketingConsent = $marketingConsent;
    }
}
