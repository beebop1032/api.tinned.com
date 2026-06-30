<?php

namespace App\Processor\Shopping;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Delivery\DeliveryMethod;
use App\Entity\Product\ProductVariant;
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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

readonly class CheckoutProcessor implements ProcessorInterface
{
    private const PAYMENT_METHODS = ['card', 'bancontact', 'paypal'];

    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private CheckoutResponseFactory $responseFactory,
        private MollieService $mollieService,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CheckoutResponse
    {
        if (!$data instanceof CheckoutRequest) {
            throw new \InvalidArgumentException('Invalid checkout payload.');
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('Authentication is required to create an order.');
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

            if ($variant->getStock() < $linePayload['quantity']) {
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
                $variant->setStock($variant->getStock() - $quantity);

                $line = (new OrderLine())
                    ->setVariant($variant)
                    ->setQuantity($quantity);
                $storeOrder->addLine($line);
                $order->addLine($line);
            }

            $storeOrder->setShippingCents($shippingCents);
            $this->em->persist($storeOrder);
        }

        $order->recalculateTotals();
        $this->applyCoupon($order, $data->couponCode);
        $this->em->flush();

        $checkoutUrl = '';
        try {
            $checkoutUrl = $this->mollieService->createPayment($order);
        } catch (\Throwable) {
            $order->setStatus(CustomerOrder::STATUS_CANCELLED);
            $this->em->flush();
            $this->fail('payment', 'Payment service is temporarily unavailable. Please try again.');
        }

        return $this->responseFactory->fromOrder($order, $checkoutUrl);
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
