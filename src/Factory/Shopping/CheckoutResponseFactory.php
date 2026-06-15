<?php

namespace App\Factory\Shopping;

use App\Entity\Shopping\CustomerOrder;
use App\Model\Shopping\CheckoutResponse;

class CheckoutResponseFactory
{
    public function fromOrder(CustomerOrder $order, ?string $checkoutUrl = null): CheckoutResponse
    {
        $items = [];
        foreach ($order->getLines() as $line) {
            $variant = $line->getVariant();
            $product = $variant?->getProduct();
            if (!$variant || !$product) {
                continue;
            }

            $items[] = [
                'productSlug' => $product->getSlug(),
                'variantSku' => $variant->getSku(),
                'quantity' => $line->getQuantity(),
            ];
        }

        $selectedStoreSlugs = [];
        $carrierSelections = [];
        $storeOrders = [];
        foreach ($order->getStoreOrders() as $storeOrder) {
            $storeBox = $storeOrder->getStoreBox();
            $storeSlug = $storeBox?->getSlug() ?? '';
            if ($storeSlug !== '') {
                $selectedStoreSlugs[] = $storeSlug;
                $carrierSelections[] = [
                    'storeSlug' => $storeSlug,
                    'carrierCode' => $storeOrder->getCarrierCode(),
                ];
            }

            $storeOrders[] = [
                'storeSlug' => $storeSlug,
                'storeName' => $storeOrder->getStoreNameSnapshot(),
                'carrierCode' => $storeOrder->getCarrierCode(),
                'carrierName' => $storeOrder->getCarrierNameSnapshot(),
                'subtotalCents' => $storeOrder->getSubtotalCents(),
                'shippingCents' => $storeOrder->getShippingCents(),
                'totalCents' => $storeOrder->getTotalCents(),
                'currency' => $storeOrder->getCurrency(),
            ];
        }

        $address = $order->getShippingAddress();
        $addressPayload = [
            'id' => $address?->getId() ? (string) $address->getId() : '',
            'label' => $address ? sprintf('%s, %s %s', $address->getStreet(), $address->getPostalCode(), $address->getCity()) : '',
            'street' => $address?->getStreet() ?? '',
            'postalCode' => $address?->getPostalCode() ?? '',
            'city' => $address?->getCity() ?? '',
            'country' => $address?->getCountryCode() ?? '',
        ];

        return new CheckoutResponse(
            orderId: $order->getId() ?? 0,
            id: $order->getReference(),
            reference: $order->getReference(),
            status: $order->getStatus(),
            paymentStatus: $order->getPaymentStatus(),
            paymentMethod: $order->getPaymentMethod(),
            email: $order->getUser()?->getEmail() ?? '',
            items: $items,
            selectedStoreSlugs: $selectedStoreSlugs,
            carrierSelections: $carrierSelections,
            address: $addressPayload,
            subtotalCents: $order->getSubtotalCents(),
            shippingCents: $order->getShippingCents(),
            totalCents: $order->getTotalCents(),
            currency: $order->getCurrency(),
            createdAt: $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
            storeOrders: $storeOrders,
            checkoutUrl: $checkoutUrl,
        );
    }
}
