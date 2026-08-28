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
    private bool $apiKeyReady = false;

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
    }

    /**
     * Client Mollie avec la clé posée paresseusement. setApiKey() valide le format et
     * LÈVE sur une clé vide/placeholder — le faire ici (et non dans le constructeur)
     * évite de faire planter en 500 tout le checkout dès l'instanciation du service :
     * une clé manquante dégrade proprement en 422 « paiement indisponible » via le
     * try/catch de CheckoutProcessor.
     */
    private function client(): MollieApiClient
    {
        if (!$this->apiKeyReady) {
            $this->mollie->setApiKey($this->apiKey);
            $this->apiKeyReady = true;
        }

        return $this->mollie;
    }

    public function createPayment(CustomerOrder $order, ?string $method = null, ?string $cardToken = null): string
    {
        $payload = [
            'amount' => $this->money($order->getTotalCents(), $order->getCurrency()),
            'description' => 'Tinned order ' . $order->getReference(),
            // The confirmation page reads ?order=<reference> (id or reference both match).
            'redirectUrl' => $this->redirectUrl . '?order=' . rawurlencode($order->getReference()),
            'webhookUrl' => $this->webhookUrl,
            'metadata' => ['orderId' => $order->getId()],
        ];

        // Pre-select the method chosen in the form so Mollie skips its own picker and sends
        // the buyer straight to that method's flow. 'mollie' (or empty) = no pre-selection,
        // i.e. the hosted page lists every enabled method (legacy behaviour, kept as fallback).
        if ($method !== null && $method !== '' && $method !== 'mollie') {
            $payload['method'] = $method;
        }

        // Card entered on our page via Mollie Components: the single-use token authorises the
        // creditcard payment. 3-D Secure (SCA) may still add a redirect via getCheckoutUrl().
        if ($cardToken !== null && $cardToken !== '') {
            $payload['cardToken'] = $cardToken;
        }

        // Send the order lines to Mollie when they reconcile exactly to the total.
        $lines = $this->buildLines($order);
        try {
            $payment = $this->client()->payments->create($lines === [] ? $payload : $payload + ['lines' => $lines]);
        } catch (ApiException) {
            // Mollie rejected the lines (validation/rounding/method): retry on the total alone,
            // which is exact. A payment must never fail because of the (optional) line details.
            $payment = $this->client()->payments->create($payload);
        }

        $order->setMolliePaymentId($payment->id);
        $this->em->flush();

        // Null when the payment completes without a redirect (card, no 3-D Secure): the front
        // then goes straight to the confirmation page.
        return $payment->getCheckoutUrl() ?? '';
    }

    /**
     * Payment methods enabled on the Mollie profile for the given amount and billing country,
     * translated to the buyer's locale, shaped for the checkout form (id + label + official
     * icons). Returns [] when Mollie can't be reached so the form can fall back to the hosted
     * flow instead of breaking.
     *
     * @return list<array{id: string, description: string, image: array{size1x: string, size2x: string}}>
     */
    public function enabledMethods(int $amountCents, string $currency, string $country, string $locale): array
    {
        try {
            $methods = $this->client()->methods->allEnabled([
                'amount' => $this->money(max(0, $amountCents), $currency),
                'locale' => $locale,
                'billingCountry' => $country,
            ]);
        } catch (\Throwable) {
            return [];
        }

        $result = [];
        foreach ($methods as $method) {
            $result[] = [
                'id' => $method->id,
                'description' => $method->description,
                'image' => [
                    'size1x' => $method->image->size1x ?? '',
                    'size2x' => $method->image->size2x ?? '',
                ],
            ];
        }

        return $result;
    }

    /**
     * Public profile id (pfl_...) of the Mollie account behind the configured API key. mollie.js
     * needs it to embed the card fields; serving it from here (we already hold the key) means the
     * front never has to configure it by hand. Null when Mollie can't be reached.
     */
    public function profileId(): ?string
    {
        try {
            return $this->client()->profiles->getCurrent()->id;
        } catch (\Throwable) {
            return null;
        }
    }

    /** True when the configured API key is a test key — mollie.js must run in the same mode. */
    public function isTestMode(): bool
    {
        return str_starts_with($this->apiKey, 'test_');
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

    public function handleWebhook(string $paymentId): void
    {
        $payment = $this->client()->payments->get($paymentId);
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
