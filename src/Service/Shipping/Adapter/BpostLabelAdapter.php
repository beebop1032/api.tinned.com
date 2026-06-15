<?php

namespace App\Service\Shipping\Adapter;

use App\Entity\Delivery\DeliveryMethod;
use App\Entity\Delivery\ShippingLabel;
use App\Service\Shipping\CarrierLabelAdapterInterface;
use App\Service\Shipping\CarrierLabelResult;
use App\Service\Shipping\ShippingLabelException;
use App\Service\Shipping\ShippingSender;
use Bpost\BpostApiClient\Bpost;
use Bpost\BpostApiClient\Bpost\Order;
use Bpost\BpostApiClient\Bpost\Order\Box;
use Bpost\BpostApiClient\Bpost\Order\Box\At247;
use Bpost\BpostApiClient\Bpost\Order\Line;
use Bpost\BpostApiClient\Bpost\Order\ParcelsDepotAddress;
use Bpost\BpostApiClient\Bpost\Order\Sender;
use Bpost\BpostApiClient\Bpost\ProductConfiguration\Product;

final readonly class BpostLabelAdapter implements CarrierLabelAdapterInterface
{
    public function __construct(
        private ShippingSender $sender,
        private string $apiUrl,
        private string $username,
        private string $password,
    ) {
    }

    public function supports(string $provider): bool
    {
        return $provider === 'bpost';
    }

    public function generate(ShippingLabel $label, DeliveryMethod $deliveryMethod): CarrierLabelResult
    {
        $this->requireConfigured($label, $deliveryMethod);
        $this->sender->requireConfigured();
        $customerOrder = $label->getStoreOrder()?->getCustomerOrder();
        $address = $customerOrder?->getShippingAddress();
        $user = $customerOrder?->getUser();
        if (!$customerOrder || !$address || !$user) {
            throw new ShippingLabelException('Les donnees destinataire Bpost sont incompletes.');
        }

        try {
            $reference = 'TIN-LBL-'.$label->getId();
            $order = new Order($reference);
            $order->addLine(new Line('Commande Tinned '.$customerOrder->getReference(), 1));
            $box = new Box();
            $box->setSender($this->bpostSender());

            $lockerAddress = new ParcelsDepotAddress();
            $lockerAddress->setStreetName(mb_substr((string) $label->getPickupPointStreet(), 0, 40));
            $lockerAddress->setNumber('1');
            $lockerAddress->setPostalCode((string) $label->getPickupPointPostalCode());
            $lockerAddress->setLocality(mb_substr((string) $label->getPickupPointCity(), 0, 40));
            $lockerAddress->setCountryCode((string) $label->getPickupPointCountryCode());

            $locker = new At247();
            $locker->setProduct(Product::PRODUCT_NAME_BPACK_24_7);
            $locker->setParcelsDepotAddress($lockerAddress);
            $locker->setParcelsDepotId((string) $label->getPickupPointId());
            $locker->setParcelsDepotName(str_replace('&', ' et ', (string) $label->getPickupPointName()));
            $locker->setWeight(min(30000, max(100, $label->getWeightGrams())));

            $box->setMessageLanguage('FR');
            $box->setMobilePhone($address->getPhone() ?? $user->getPhone() ?? '');
            $box->setEmail($user->getEmail() ?? '');
            $box->setReceiverName(trim($address->getFirstName().' '.$address->getLastName()));
            $box->setRequestedDeliveryDate($this->deliveryDate());
            $box->setNationalBox($locker);
            $order->addBox($box);

            $client = new Bpost($this->username, $this->password, $this->apiUrl);
            if (!$client->createOrReplaceOrder($order)) {
                throw new ShippingLabelException('Bpost n a pas accepte l expedition.');
            }
            $documents = $client->createLabelForOrder(
                $reference,
                $label->getFormat() === 'A4' ? Bpost::LABEL_FORMAT_A4 : Bpost::LABEL_FORMAT_A6,
                false,
                true,
            );
            $document = $documents[0] ?? null;
            if (!$document) {
                throw new ShippingLabelException('Bpost n a retourne aucune etiquette.');
            }
            $barcode = $document->getBarcode() ?: null;
        } catch (ShippingLabelException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new ShippingLabelException('Bpost a refuse la generation: '.$exception->getMessage(), previous: $exception);
        }

        return new CarrierLabelResult(
            (string) $document->getBytes(),
            $barcode,
            $barcode ? sprintf(
                'https://track.bpost.cloud/btr/web/#/search?lang=fr&postalCode=%s&itemCode=%s',
                rawurlencode((string) $label->getPickupPointPostalCode()),
                rawurlencode($barcode),
            ) : null,
        );
    }

    private function requireConfigured(ShippingLabel $label, DeliveryMethod $deliveryMethod): void
    {
        if ($this->apiUrl === '' || $this->username === '' || $this->password === '') {
            throw new ShippingLabelException('Bpost n est pas configure pour Tinned.');
        }
        if ($deliveryMethod->getMethod() !== DeliveryMethod::METHOD_LOCKER) {
            throw new ShippingLabelException('Seules les etiquettes Bpost locker sont actuellement activees.');
        }
        foreach ([$label->getPickupPointId(), $label->getPickupPointName(), $label->getPickupPointStreet(), $label->getPickupPointPostalCode(), $label->getPickupPointCity(), $label->getPickupPointCountryCode()] as $value) {
            if (!$value) {
                throw new ShippingLabelException('Renseignez le distributeur Bpost avant de generer l etiquette.');
            }
        }
    }

    private function bpostSender(): Sender
    {
        $address = new Order\Address();
        $address->setStreetName(mb_substr($this->sender->street, 0, 40));
        $address->setNumber(mb_substr($this->sender->streetNumber, 0, 8));
        $address->setPostalCode($this->sender->postalCode);
        $address->setLocality(mb_substr($this->sender->city, 0, 40));
        $address->setCountryCode($this->sender->countryCode);

        $sender = new Sender();
        $sender->setName($this->sender->name);
        $sender->setAddress($address);
        $sender->setPhoneNumber(mb_substr($this->sender->phone, 0, 20));
        $sender->setEmailAddress(mb_substr($this->sender->email, 0, 50));

        return $sender;
    }

    private function deliveryDate(): string
    {
        $date = new \DateTimeImmutable('today');
        for ($days = 0; $days < 2;) {
            $date = $date->modify('+1 day');
            if ((int) $date->format('N') < 6) {
                ++$days;
            }
        }

        return $date->format('Y-m-d');
    }
}
