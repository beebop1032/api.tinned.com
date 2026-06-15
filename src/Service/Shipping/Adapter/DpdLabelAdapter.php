<?php

namespace App\Service\Shipping\Adapter;

use App\Entity\Delivery\DeliveryMethod;
use App\Entity\Delivery\ShippingLabel;
use App\Service\Shipping\CarrierLabelAdapterInterface;
use App\Service\Shipping\CarrierLabelResult;
use App\Service\Shipping\ShippingLabelException;
use App\Service\Shipping\ShippingSender;

final readonly class DpdLabelAdapter implements CarrierLabelAdapterInterface
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
        return $provider === 'dpd';
    }

    public function generate(ShippingLabel $label, DeliveryMethod $deliveryMethod): CarrierLabelResult
    {
        $this->requireConfigured();
        $this->sender->requireConfigured();

        $customerOrder = $label->getStoreOrder()?->getCustomerOrder();
        $address = $customerOrder?->getShippingAddress();
        $user = $customerOrder?->getUser();
        if (!$customerOrder || !$address || !$user) {
            throw new ShippingLabelException('Les donnees destinataire DPD sont incompletes.');
        }

        try {
            $loginClient = new \SoapClient(rtrim($this->apiUrl, '/').'/WSDL/LoginServiceV21.wsdl');
            $auth = $loginClient->getAuth([
                'delisId' => $this->username,
                'password' => $this->password,
                'messageLanguage' => 'fr-be',
            ]);

            $client = new \SoapClient(rtrim($this->apiUrl, '/').'/WSDL/ShipmentServiceV34.wsdl');
            $client->__setSoapHeaders(new \SoapHeader(
                'http://dpd.com/common/service/types/Authentication/2.0',
                'authentication',
                [
                    'delisId' => $auth->return->delisId,
                    'authToken' => $auth->return->authToken,
                    'messageLanguage' => 'fr-be',
                ],
            ));

            $response = $client->storeOrders([
                'printOptions' => [
                    'printerLanguage' => 'PDF',
                    'paperFormat' => $label->getFormat() === 'A4' ? 'A4' : 'A6',
                ],
                'order' => [
                    'generalShipmentData' => [
                        'sendingDepot' => (string) $auth->return->depot,
                        'identificationNumber' => $customerOrder->getReference(),
                        'product' => strtoupper($address->getCountryCode()) === 'BE' ? 'DRY' : 'CL',
                        'mpsCustomerReferenceNumber1' => $customerOrder->getReference(),
                        'sender' => $this->party(
                            $this->sender->name,
                            $this->sender->street,
                            $this->sender->countryCode,
                            $this->sender->postalCode,
                            $this->sender->city,
                            $this->sender->phone,
                            $this->sender->email,
                        ),
                        'recipient' => $this->party(
                            trim($address->getFirstName().' '.$address->getLastName()),
                            $address->getStreet(),
                            $address->getCountryCode(),
                            $address->getPostalCode(),
                            $address->getCity(),
                            $address->getPhone() ?? $user->getPhone() ?? '',
                            $user->getEmail() ?? '',
                        ),
                    ],
                    'parcels' => [[
                        'customerReferenceNumber1' => $customerOrder->getReference(),
                        'weight' => max(10, min(3000, (int) ceil($label->getWeightGrams() / 10))),
                        'returns' => false,
                    ]],
                    'productAndServiceData' => ['orderType' => 'consignment'],
                ],
            ]);
        } catch (\SoapFault $exception) {
            throw new ShippingLabelException('DPD a refuse la generation: '.$exception->getMessage(), previous: $exception);
        }

        $result = $response->orderResult ?? null;
        $tracking = $result?->shipmentResponses?->parcelInformation?->parcelLabelNumber ?? null;
        if (is_array($result?->shipmentResponses?->parcelInformation ?? null)) {
            $tracking = $result->shipmentResponses->parcelInformation[0]->parcelLabelNumber ?? null;
        }

        return new CarrierLabelResult(
            (string) ($result?->parcellabelsPDF ?? ''),
            $tracking ? (string) $tracking : null,
            $tracking ? 'https://www.dpdgroup.com/be/mydpd/my-parcels/search?lang=fr&parcelNumber='.rawurlencode((string) $tracking) : null,
        );
    }

    private function requireConfigured(): void
    {
        if (trim($this->apiUrl) === '' || trim($this->username) === '' || trim($this->password) === '') {
            throw new ShippingLabelException('DPD n est pas configure pour Tinned.');
        }
    }

    /** @return array<string, string> */
    private function party(string $name, string $street, string $country, string $postalCode, string $city, string $phone, string $email): array
    {
        return [
            'name1' => mb_substr($name, 0, 35),
            'street' => mb_substr($street, 0, 35),
            'houseNo' => '',
            'country' => strtoupper(mb_substr($country, 0, 2)),
            'zipCode' => mb_substr($postalCode, 0, 9),
            'city' => mb_substr($city, 0, 35),
            'type' => 'P',
            'phone' => mb_substr($phone, 0, 35),
            'email' => mb_substr($email, 0, 100),
        ];
    }
}
