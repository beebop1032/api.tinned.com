<?php

namespace App\Service\Payment;

use App\Entity\Shopping\CustomerOrder;
use App\Entity\Shopping\PayoutLedgerEntry;
use App\Entity\Shopping\StoreOrder;
use App\Service\Shopping\InvoiceNumberAllocator;
use App\Service\Shopping\OrderInventoryReleaser;
use App\Service\Shopping\OrderMailer;
use Doctrine\ORM\EntityManagerInterface;
use Mollie\Api\Exceptions\ApiException;
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
        $payload = [
            'amount' => $this->money($order->getTotalCents(), $order->getCurrency()),
            'description' => 'Tinned order ' . $order->getReference(),
            // Pre-select the buyer's chosen method so Mollie opens straight on it
            // (Bancontact dominates in BE) instead of defaulting to card.
            'method' => $this->preferredMethod($order),
            // The confirmation page reads ?order=<reference> (id or reference both match).
            'redirectUrl' => $this->redirectUrl . '?order=' . rawurlencode($order->getReference()),
            'webhookUrl' => $this->webhookUrl,
            'metadata' => ['orderId' => $order->getId()],
        ];

        // Send the order lines to Mollie when they reconcile exactly to the total.
        $lines = $this->buildLines($order);
        try {
            $payment = $this->mollie->payments->create($lines === [] ? $payload : $payload + ['lines' => $lines]);
        } catch (ApiException) {
            // Mollie rejected the lines (validation/rounding/method): retry on the total alone,
            // which is exact. A payment must never fail because of the (optional) line details.
            $payment = $this->mollie->payments->create($payload);
        }

        $order->setMolliePaymentId($payment->id);
        $this->em->flush();

        return $payment->getCheckoutUrl();
    }

    /** A Mollie amount object from integer cents (2-decimal string, dot separator). */
    private function money(int $cents, string $currency): array
    {
        return ['currency' => $currency, 'value' => number_format($cents / 100, 2, '.', '')];
    }

    /**
     * Builds Mollie payment lines (products + shipping + order discount) from the order,
     * with amounts in Mollie's own convention: prices are VAT-inclusive, so each line's
     * vatAmount = round(totalAmount × rate / (100 + rate)). Returns [] (→ send the total
     * only) if the lines don't reconcile exactly to the order total, so Mollie never rejects
     * on a cent mismatch.
     *
     * @return list<array<string, mixed>>
     */
    private function buildLines(CustomerOrder $order): array
    {
        $currency = $order->getCurrency();
        $lines = [];
        $sumCents = 0;

        foreach ($order->getLines() as $line) {
            $quantity = $line->getQuantity();
            $unitCents = $line->getUnitPriceCentsSnapshot();
            $totalCents = $unitCents * $quantity;
            $rate = $line->getVatRatePercent();
            $lines[] = [
                'type' => 'physical',
                'description' => $line->getProductNameSnapshot() ?: 'Article',
                'quantity' => $quantity,
                'unitPrice' => $this->money($unitCents, $currency),
                'totalAmount' => $this->money($totalCents, $currency),
                'vatRate' => number_format($rate, 2, '.', ''),
                'vatAmount' => $this->money($this->vatOf($totalCents, $rate), $currency),
            ];
            $sumCents += $totalCents;
        }

        $shippingCents = $order->getShippingCents();
        if ($shippingCents > 0) {
            $rate = 21; // Livraison au taux standard (comme la facture).
            $lines[] = [
                'type' => 'shipping_fee',
                'description' => 'Frais de livraison',
                'quantity' => 1,
                'unitPrice' => $this->money($shippingCents, $currency),
                'totalAmount' => $this->money($shippingCents, $currency),
                'vatRate' => number_format($rate, 2, '.', ''),
                'vatAmount' => $this->money($this->vatOf($shippingCents, $rate), $currency),
            ];
            $sumCents += $shippingCents;
        }

        // Order-wide discount (coupon/bundle) as a negative line. The pre-order −15% is NOT
        // here: it is already baked into each pre-order line's unit price.
        $discountCents = $order->getDiscountCents();
        if ($discountCents > 0) {
            $rate = 21;
            $lines[] = [
                'type' => 'discount',
                'description' => $order->getCouponCode() ? 'Remise ' . $order->getCouponCode() : 'Remise',
                'quantity' => 1,
                'unitPrice' => $this->money(-$discountCents, $currency),
                'totalAmount' => $this->money(-$discountCents, $currency),
                'vatRate' => number_format($rate, 2, '.', ''),
                'vatAmount' => $this->money(-$this->vatOf($discountCents, $rate), $currency),
            ];
            $sumCents -= $discountCents;
        }

        return $sumCents === $order->getTotalCents() ? $lines : [];
    }

    /** VAT included in a VAT-inclusive amount, rounded Mollie's way. */
    private function vatOf(int $ttcCents, int $ratePercent): int
    {
        return (int) round($ttcCents * $ratePercent / (100 + $ratePercent));
    }

    /**
     * Resolves the Mollie method to pre-select: the buyer's explicit choice when
     * set, otherwise a country-aware default (Bancontact in Belgium, card elsewhere).
     */
    /**
     * Records the frozen commission/payout line for a paid store order (once).
     */
    private function recordPayout(StoreOrder $storeOrder): void
    {
        $storeBox = $storeOrder->getStoreBox();
        if (!$storeBox) {
            return;
        }
        if ($this->em->getRepository(PayoutLedgerEntry::class)->findOneBy(['storeOrder' => $storeOrder])) {
            return;
        }

        $gross = $storeOrder->getTotalCents();
        $rate = $storeBox->getCommissionRatePercent();
        $commission = (int) round($gross * $rate / 100);

        $this->em->persist(
            (new PayoutLedgerEntry())
                ->setStoreOrder($storeOrder)
                ->setStoreBox($storeBox)
                ->setStoreReference($storeOrder->getCustomerOrder()?->getReference() ?? '')
                ->setGrossCents($gross)
                ->setCommissionCents($commission)
                ->setNetCents($gross - $commission)
                ->setCommissionRatePercent($rate)
        );
    }

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
                    // Freeze the commission/payout accounting line for this store order.
                    $this->recordPayout($storeOrder);
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
