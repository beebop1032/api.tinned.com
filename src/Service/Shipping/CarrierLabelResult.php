<?php

namespace App\Service\Shipping;

final readonly class CarrierLabelResult
{
    public function __construct(
        public string $pdfContent,
        public ?string $trackingNumber = null,
        public ?string $trackingUrl = null,
    ) {
    }
}
