<?php

namespace App\Service\Shipping;

use App\Entity\Delivery\DeliveryMethod;
use App\Entity\Delivery\ShippingLabel;

interface CarrierLabelAdapterInterface
{
    public function supports(string $provider): bool;

    public function generate(ShippingLabel $label, DeliveryMethod $deliveryMethod): CarrierLabelResult;
}
