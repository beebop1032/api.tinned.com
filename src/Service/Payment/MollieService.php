<?php

namespace App\Service\Payment;

use App\Entity\Shopping\CustomerOrder;
use App\Entity\Shopping\StoreOrder;
use App\Service\Shopping\InvoiceNumberAllocator;
use App\Service\Shopping\OrderInventoryReleaser;
use App\Service\Shopping\OrderMailer;
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
        private OrderInventoryReleaser $inventoryReleaser,
        private OrderMailer $orderMailer,
        private InvoiceNumberAllocator $invoiceNumberAllocator,
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
            // Only advance once: a replayed webhook must not reset store orders that the
            // seller has already moved to preparing/shipped.
            if ($order->getStatus() !== CustomerOrder::STATUS_PAID) {
                $order->setStatus(CustomerOrder::STATUS_PAID);
                // Assign the legal invoice number once, on first payment confirmation.
                if ($order->getInvoiceNumber() === null) {
                    $order->setInvoiceNumber($this->invoiceNumberAllocator->allocate());
                }
                foreach ($order->getStoreOrders() as $storeOrder) {
                    if ($storeOrder->getStatus() === StoreOrder::STATUS_OPEN) {
                        $storeOrder->setStatus(StoreOrder::STATUS_WAITING_STORE);
                    }
                    // Notify each store owner of the new payable order.
                    $this->orderMailer->sendNewOrderToSeller($storeOrder);
                }
                // Payment receipt to the buyer.
                $this->orderMailer->sendPaymentReceipt($order);
            }
        } elseif ($payment->isFailed() || $payment->isExpired() || $payment->isCanceled()) {
            // Never cancel (or re-release) an order that has already been paid, e.g. an
            // out-of-order or duplicate webhook delivery.
            if ($order->getStatus() !== CustomerOrder::STATUS_PAID) {
                $order->setStatus(CustomerOrder::STATUS_CANCELLED);
                foreach ($order->getStoreOrders() as $storeOrder) {
                    if (!in_array($storeOrder->getStatus(), [StoreOrder::STATUS_SHIPPED, StoreOrder::STATUS_COMPLETED], true)) {
                        $storeOrder->setStatus(StoreOrder::STATUS_CANCELLED);
                    }
                }
                // Idempotent (guarded by CustomerOrder::inventoryReleased).
                $this->inventoryReleaser->release($order);
            }
        }

        $this->em->flush();
    }
}
