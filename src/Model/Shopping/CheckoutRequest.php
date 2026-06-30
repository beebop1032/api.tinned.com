<?php

namespace App\Model\Shopping;

use Symfony\Component\Validator\Constraints as Assert;

class CheckoutRequest
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email = '';

    #[Assert\NotBlank]
    public string $firstName = '';

    #[Assert\NotBlank]
    public string $lastName = '';

    public ?string $phone = null;

    /** @var array{street?: string, postalCode?: string, city?: string, country?: string, countryCode?: string} */
    #[Assert\Type('array')]
    public array $address = [];

    /** @var list<array{variantSku?: string, quantity?: int}> */
    #[Assert\Count(min: 1)]
    public array $items = [];

    /** @var list<array{storeSlug?: string, carrierCode?: string}> */
    public array $carrierSelections = [];

    /** @var list<string> */
    public array $selectedStoreSlugs = [];

    #[Assert\NotBlank]
    public string $paymentMethod = 'card';

    public ?string $couponCode = null;
}
