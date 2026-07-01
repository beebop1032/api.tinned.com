<?php

namespace App\Processor\Shopping;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Delivery\DeliveryMethod;
use App\Entity\Product\ProductVariant;
use App\Entity\Product\StockMovement;
use App\Entity\Shopping\Coupon;
use App\Entity\Shopping\CustomerOrder;
use App\Entity\Shopping\OrderLine;
use App\Entity\Shopping\StoreOrder;
use App\Entity\User;
use App\Entity\User\Address;
use App\Factory\Shopping\CheckoutResponseFactory;
use App\Model\Shopping\CheckoutRequest;
use App\Model\Shopping\CheckoutResponse;
use App\Service\Payment\MollieService;
use App\Service\Shopping\OrderInventoryReleaser;
use App\Service\Shopping\OrderMailer;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

readonly class CheckoutProcessor implements ProcessorInterface
{
    private const PAYMENT_METHODS = ['card', 'bancontact', 'paypal', 'kbc', 'belfius', 'ideal'];

    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private CheckoutResponseFactory $responseFactory,
        private MollieService $mollieService,
        private OrderInventoryReleaser $inventoryReleaser,
        private OrderMailer $orderMailer,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CheckoutResponse
    {
        if (!$data instanceof CheckoutRequest) {
            throw new \InvalidArgumentException('Invalid checkout payload.');
        }

        // Logged-in buyer, or a guest resolved/created from the provided email.
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            $user = $this->resolveGuestUser($data);
        }

        if (!in_array($data->paymentMethod, self::PAYMENT_METHODS, true)) {
            $this->fail('paymentMethod', 'Unsupported payment method.');
        }

        $addressPayload = $this->normaliseAddress($data->address);
        $linePayloads = $this->normaliseItems($data->items);
        $selectedStoreSlugs = $this->normaliseStringList($data->selectedStoreSlugs);
        $carrierByStore = $this->normaliseCarrierSelections($data->carrierSelections);
        $availableDeliveryMethods = $this->availableDeliveryMethods($addressPayload['countryCode']);

        $variantRepository = $this->em->getRepository(ProductVariant::class);
        $stores = [];
        foreach ($linePayloads as $linePayload) {
            /** @var ProductVariant|null $variant */
            $variant = $variantRepository->findOneBy(['sku' => $linePayload['variantSku']]);
            if (!$variant) {
                $this->fail('items', sprintf('Variant %s was not found.', $linePayload['variantSku']));
            }

            $product = $variant->getProduct();
            $storeBox = $product?->getStoreBox();
            if (!$product || !$storeBox || !$product->isActive() || !$variant->isActive()) {
                $this->fail('items', sprintf('Variant %s is not available.', $linePayload['variantSku']));
            }

            if ($selectedStoreSlugs !== [] && !in_array($storeBox->getSlug(), $selectedStoreSlugs, true)) {
                continue;
            }

            // Coming-soon products are not buyable yet: reject the line outright.
            if ($product->getAvailability() === 'coming_soon') {
                $this->fail('items', sprintf('%s n\'est pas encore disponible.', $product->getName()));
            }

            // Pre-order products can be bought ahead of stock: skip the stock-availability
            // guard. Any other availability ('available') keeps the original behaviour.
            if ($product->getAvailability() !== 'preorder' && $variant->getStock() < $linePayload['quantity']) {
                $this->fail('items', sprintf('Only %d item(s) left for %s.', $variant->getStock(), $variant->getSku()));
            }

            $storeSlug = $storeBox->getSlug();
            if (!isset($stores[$storeSlug])) {
                $stores[$storeSlug] = [
                    'storeBox' => $storeBox,
                    'lines' => [],
                    'subtotalCents' => 0,
                    'currency' => $product->getCurrency(),
                ];
            }

            $stores[$storeSlug]['lines'][] = [
                'variant' => $variant,
                'quantity' => $linePayload['quantity'],
            ];
            $stores[$storeSlug]['subtotalCents'] += $variant->getPriceCents() * $linePayload['quantity'];
        }

        if ($stores === []) {
            $this->fail('items', 'No selected item can be ordered.');
        }

        $address = (new Address())
            ->setUser($user)
            ->setFirstName($data->firstName)
            ->setLastName($data->lastName)
            ->setStreet($addressPayload['street'])
            ->setPostalCode($addressPayload['postalCode'])
            ->setCity($addressPayload['city'])
            ->setCountryCode($addressPayload['countryCode'])
            ->setPhone($data->phone);

        $order = (new CustomerOrder())
            ->setUser($user)
            ->setShippingAddress($address)
            ->setStatus(CustomerOrder::STATUS_PENDING_PAYMENT)
            ->setPaymentStatus('pending')
            ->setPaymentMethod($data->paymentMethod);

        // Reserve stock atomically: a single transaction with a row lock per variant
        // prevents two concurrent checkouts from overselling the same units.
        $this->em->beginTransaction();
        try {
            $this->em->persist($address);
            $this->em->persist($order);

            foreach ($stores as $storeSlug => $storeData) {
                $carrier = $this->deliveryMethodFor($carrierByStore[$storeSlug] ?? null, $availableDeliveryMethods);
                $shippingCents = $carrier->priceFor((int) $storeData['subtotalCents']);

                $storeOrder = (new StoreOrder())
                    ->setStoreBox($storeData['storeBox'])
                    ->setCarrierCode($carrier->getCode())
                    ->setCarrierNameSnapshot($carrier->getName())
                    ->setDeliveryMode($carrier->getMethod())
                    ->setCurrency($storeData['currency']);

                $order->addStoreOrder($storeOrder);

                foreach ($storeData['lines'] as $storeLine) {
                    /** @var ProductVariant $variant */
                    $variant = $storeLine['variant'];
                    $quantity = (int) $storeLine['quantity'];

                    // Lock the row, then re-check stock against the locked value so the
                    // guard holds under concurrency (the earlier check is only fail-fast).
                    $this->em->lock($variant, LockMode::PESSIMISTIC_WRITE);
                    $isPreorder = $variant->getProduct()?->getAvailability() === 'preorder';
                    if (!$isPreorder && $variant->getStock() < $quantity) {
                        $this->fail('items', sprintf('Only %d item(s) left for %s.', $variant->getStock(), $variant->getSku()));
                    }

                    // Pre-orders may exceed available stock; clamp at 0 so stock never goes
                    // negative. Record what was actually removed so it can be given back exactly.
                    $before = $variant->getStock();
                    $variant->setStock(max(0, $before - $quantity));
                    $reserved = $before - $variant->getStock();
                    $this->logSaleMovement($variant, $reserved, $storeOrder->getStoreBox()?->getSlug());

                    $line = (new OrderLine())
                        ->setVariant($variant)
                        ->setQuantity($quantity)
                        ->setStockReserved($reserved)
                        ->setVatRatePercent($variant->getProduct()?->getVatRatePercent() ?? 21);
                    $storeOrder->addLine($line);
                    $order->addLine($line);
                }

                $storeOrder->setShippingCents($shippingCents);
                $this->em->persist($storeOrder);
            }

            $order->recalculateTotals();
            $this->applyCoupon($order, $data->couponCode);
            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        $checkoutUrl = '';
        try {
            $checkoutUrl = $this->mollieService->createPayment($order);
        } catch (\Throwable) {
            // Payment could not be initiated: cancel and give the reserved stock/coupon back.
            $order->setStatus(CustomerOrder::STATUS_CANCELLED);
            $this->inventoryReleaser->release($order);
            $this->em->flush();
            $this->fail('payment', 'Payment service is temporarily unavailable. Please try again.');
        }

        // Order received (payment still pending). Best-effort: never blocks the response.
        $this->orderMailer->sendOrderReceived($order);

        return $this->responseFactory->fromOrder($order, $checkoutUrl);
    }

    /**
     * Resolves the buyer for a guest (unauthenticated) checkout: reuses the account that
     * already owns this email, or creates a passwordless guest the buyer can later claim
     * by setting a password. The random password placeholder can never authenticate.
     */
    private function resolveGuestUser(CheckoutRequest $data): User
    {
        $email = strtolower(trim($data->email));
        if ($email === '') {
            $this->fail('email', 'A valid email is required to place an order.');
        }

        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing instanceof User) {
            return $existing;
        }

        $user = (new User())
            ->setEmail($email)
            ->setFirstName($data->firstName)
            ->setLastName($data->lastName)
            ->setPhone($data->phone);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword(bin2hex(random_bytes(16)));
        $this->em->persist($user);

        return $user;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{street: string, postalCode: string, city: string, countryCode: string}
     */
    private function normaliseAddress(array $payload): array
    {
        $street = trim((string) ($payload['street'] ?? ''));
        $postalCode = trim((string) ($payload['postalCode'] ?? ''));
        $city = trim((string) ($payload['city'] ?? ''));
        $countryCode = strtoupper(trim((string) ($payload['countryCode'] ?? $payload['country'] ?? 'BE')));

        if ($street === '' || $postalCode === '' || $city === '' || !preg_match('/^[A-Z]{2}$/', $countryCode)) {
            $this->fail('address', 'A complete delivery address is required.');
        }

        return compact('street', 'postalCode', 'city', 'countryCode');
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array{variantSku: string, quantity: int}>
     */
    private function normaliseItems(array $items): array
    {
        $quantitiesBySku = [];
        foreach ($items as $item) {
            $sku = trim((string) ($item['variantSku'] ?? ''));
            $quantity = max(1, min(99, (int) ($item['quantity'] ?? 1)));
            if ($sku === '') {
                $this->fail('items', 'Each order line requires a variant SKU.');
            }

            $quantitiesBySku[$sku] = ($quantitiesBySku[$sku] ?? 0) + $quantity;
        }

        $lines = [];
        foreach ($quantitiesBySku as $sku => $quantity) {
            $lines[] = ['variantSku' => $sku, 'quantity' => min(99, $quantity)];
        }

        return $lines;
    }

    /**
     * @param list<mixed> $values
     * @return list<string>
     */
    private function normaliseStringList(array $values): array
    {
        return array_values(array_unique(array_filter($values, static fn (mixed $value): bool => is_string($value) && $value !== '')));
    }

    /**
     * @param list<array<string, mixed>> $selections
     * @return array<string, string>
     */
    private function normaliseCarrierSelections(array $selections): array
    {
        $carrierByStore = [];
        foreach ($selections as $selection) {
            $storeSlug = trim((string) ($selection['storeSlug'] ?? ''));
            $carrierCode = trim((string) ($selection['carrierCode'] ?? ''));
            if ($storeSlug === '' || $carrierCode === '') {
                continue;
            }

            $carrierByStore[$storeSlug] = $carrierCode;
        }

        return $carrierByStore;
    }

    /** @return list<DeliveryMethod> */
    private function availableDeliveryMethods(string $countryCode): array
    {
        $methods = $this->em->getRepository(DeliveryMethod::class)->findBy(
            ['countryCode' => $countryCode, 'active' => true],
            ['position' => 'ASC'],
        );
        if ($methods === []) {
            $this->fail('address.country', sprintf('No delivery method is available for %s.', $countryCode));
        }

        return $methods;
    }

    /** @param list<DeliveryMethod> $availableDeliveryMethods */
    private function deliveryMethodFor(?string $code, array $availableDeliveryMethods): DeliveryMethod
    {
        if ($code) {
            foreach ($availableDeliveryMethods as $method) {
                if ($method->getCode() === $code) {
                    return $method;
                }
            }
            $this->fail('carrierSelections', sprintf('Delivery method %s is unavailable for this address.', $code));
        }

        foreach ($availableDeliveryMethods as $method) {
            if ($method->isRecommended()) {
                return $method;
            }
        }

        return $availableDeliveryMethods[0];
    }

    /**
     * Applies an optional promo code to the order. When the code is empty or
     * invalid the order keeps a discount of 0 and behaves exactly as before.
     */
    private function applyCoupon(CustomerOrder $order, ?string $rawCode): void
    {
        $code = strtoupper(trim((string) $rawCode));
        if ($code === '') {
            return;
        }

        $coupon = $this->em->getRepository(Coupon::class)->findOneBy(['code' => $code]);
        if (!$coupon instanceof Coupon
            || !$coupon->isValidNow()
            || $order->getSubtotalCents() < $coupon->getMinSubtotalCents()
        ) {
            return;
        }

        $order->setDiscountCents($coupon->discountFor($order->getSubtotalCents()));
        $order->setCouponCode($coupon->getCode());
        $coupon->incrementUsedCount();
    }

    /**
     * Records a `sale` stock movement for the just-decremented variant. The
     * stock is already updated by the caller, so this only snapshots it — it
     * never re-applies the delta. Wrapped in a try/catch so a logging failure
     * can never break an otherwise valid checkout.
     */
    private function logSaleMovement(ProductVariant $variant, int $quantity, ?string $storeSlug): void
    {
        try {
            $movement = (new StockMovement())
                ->setVariant($variant)
                ->setDelta(-$quantity)
                ->setReason('sale')
                ->setResultingStock($variant->getStock())
                ->setNote($storeSlug ? sprintf('Vente boutique %s', $storeSlug) : null);
            $this->em->persist($movement);
        } catch (\Throwable) {
            // Stock-movement logging is additive; never let it break a checkout.
        }
    }

    private function fail(string $path, string $message): never
    {
        throw new ValidationException(new ConstraintViolationList([
            new ConstraintViolation(
                message: $message,
                messageTemplate: null,
                parameters: [],
                root: null,
                propertyPath: $path,
                invalidValue: null,
            ),
        ]));
    }
}
