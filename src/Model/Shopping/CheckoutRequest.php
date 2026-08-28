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

    // Mollie method id chosen in the form (e.g. 'creditcard', 'bancontact', 'ideal'), or
    // 'mollie' (default) to let Mollie's hosted page list every enabled method.
    #[Assert\NotBlank]
    public string $paymentMethod = 'mollie';

    // Single-use Mollie Components token, sent only when paymentMethod is 'creditcard' and
    // the card was entered on our page. Empty for redirect methods.
    public ?string $cardToken = null;

    public ?string $couponCode = null;
}
