<?php

namespace App\Model\Shopping;

class CheckoutResponse
{
    /**
     * @param list<array{productSlug: string, variantSku: string, quantity: int}> $items
     * @param list<string> $selectedStoreSlugs
     * @param list<array{storeSlug: string, carrierCode: ?string}> $carrierSelections
     * @param array{id: string, label: string, street: string, postalCode: string, city: string, country: string} $address
     * @param list<array{storeSlug: string, storeName: string, carrierCode: ?string, carrierName: ?string, subtotalCents: int, shippingCents: int, totalCents: int, currency: string}> $storeOrders
     */
    public function __construct(
        public int $orderId,
        public string $id,
        public string $reference,
        public string $status,
        public string $paymentStatus,
        public ?string $paymentMethod,
        public string $email,
        public array $items,
        public array $selectedStoreSlugs,
        public array $carrierSelections,
        public array $address,
        public int $subtotalCents,
        public int $shippingCents,
        public int $totalCents,
        public string $currency,
        public string $createdAt,
        public array $storeOrders = [],
        public ?string $checkoutUrl = null,
    ) {}
}
