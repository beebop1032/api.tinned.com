<?php

namespace App\Service\Payment;

use App\Entity\Shopping\CustomerOrder;
use App\Entity\Shopping\StoreOrder;
use Doctrine\ORM\EntityManagerInterface;
use Mollie\Api\MollieApiClient;

class MollieService
{
    private MollieApiClient $mollie;

    public function __construct(
        private string $apiKey,
        private string $redirectUrl,
        private string $webhookUrl,
        private EntityManagerInterface $em,
    ) {
        $this->mollie = new MollieApiClient();
        $this->mollie->setApiKey($this->apiKey);
    }

    public function createPayment(CustomerOrder $order): string
    {
        $payment = $this->mollie->payments->create([
            'amount' => [
                'currency' => $order->getCurrency(),
                'value' => number_format($order->getTotalCents() / 100, 2, '.', ''),
            ],
            'description' => 'Tinned order ' . $order->getReference(),
            // Pre-select the buyer's chosen method so Mollie opens straight on it
            // (Bancontact dominates in BE) instead of defaulting to card.
            'method' => $this->preferredMethod($order),
            // The confirmation page reads ?order=<reference> (id or reference both match).
            'redirectUrl' => $this->redirectUrl . '?order=' . rawurlencode($order->getReference()),
            'webhookUrl' => $this->webhookUrl,
            'metadata' => ['orderId' => $order->getId()],
        ]);

        $order->setMolliePaymentId($payment->id);
        $this->em->flush();

        return $payment->getCheckoutUrl();
    }

    /**
     * Resolves the Mollie method to pre-select: the buyer's explicit choice when
     * set, otherwise a country-aware default (Bancontact in Belgium, card elsewhere).
     */
    private function preferredMethod(CustomerOrder $order): string
    {
        $chosen = $order->getPaymentMethod();
        if (is_string($chosen) && $chosen !== '') {
            return $chosen;
        }

        $countryCode = $order->getShippingAddress()?->getCountryCode();

        return $countryCode === 'BE' ? 'bancontact' : 'card';
    }

    public function handleWebhook(string $paymentId): void
    {
        $payment = $this->mollie->payments->get($paymentId);
        $orderId = $payment->metadata->orderId ?? null;
        if (!$orderId) {
            return;
        }

        $order = $this->em->find(CustomerOrder::class, (int) $orderId);
        if (!$order) {
            return;
        }

        $order->setPaymentStatus($payment->status);

        if ($payment->isPaid()) {
            $order->setStatus(CustomerOrder::STATUS_PAID);
            foreach ($order->getStoreOrders() as $storeOrder) {
                $storeOrder->setStatus(StoreOrder::STATUS_WAITING_STORE);
            }
        } elseif ($payment->isFailed() || $payment->isExpired() || $payment->isCanceled()) {
            $order->setStatus(CustomerOrder::STATUS_CANCELLED);
        }

        $this->em->flush();
    }
}
