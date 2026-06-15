<?php

namespace App\Service\Shipping\Adapter;

use App\Entity\Delivery\DeliveryMethod;
use App\Entity\Delivery\ShippingLabel;
use App\Service\Shipping\CarrierLabelAdapterInterface;
use App\Service\Shipping\CarrierLabelResult;
use App\Service\Shipping\ShippingLabelException;
use App\Service\Shipping\ShippingSender;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class MondialRelayLabelAdapter implements CarrierLabelAdapterInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ShippingSender $sender,
        private string $apiUrl,
        private string $brandId,
        private string $login,
        private string $password,
    ) {
    }

    public function supports(string $provider): bool
    {
        return $provider === 'mondial_relay';
    }

    public function generate(ShippingLabel $label, DeliveryMethod $deliveryMethod): CarrierLabelResult
    {
        $this->requireConfigured($label);
        $this->sender->requireConfigured();
        $order = $label->getStoreOrder()?->getCustomerOrder();
        $address = $order?->getShippingAddress();
        $user = $order?->getUser();
        if (!$order || !$address || !$user) {
            throw new ShippingLabelException('Les donnees destinataire Mondial Relay sont incompletes.');
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        $root = $document->appendChild($document->createElement('ShipmentCreationRequest'));
        $root->setAttribute('xmlns', 'http://www.example.org/Request');
        $context = $root->appendChild($document->createElement('Context'));
        $this->elements($document, $context, [
            'Login' => $this->login,
            'Password' => $this->password,
            'CustomerId' => $this->brandId,
            'Culture' => 'fr-FR',
            'VersionAPI' => '1.0',
        ]);
        $output = $root->appendChild($document->createElement('OutputOptions'));
        $this->elements($document, $output, ['OutputFormat' => '10x15', 'OutputType' => 'PdfUrl']);
        $shipment = $root->appendChild($document->createElement('ShipmentsList'))->appendChild($document->createElement('Shipment'));
        $this->elements($document, $shipment, [
            'OrderNo' => 'TIN-'.$label->getId(),
            'CustomerNo' => '#'.$user->getId(),
            'ParcelCount' => '1',
        ]);
        $deliveryMode = $shipment->appendChild($document->createElement('DeliveryMode'));
        $deliveryMode->setAttribute('Mode', '24R');
        $deliveryMode->setAttribute('Location', strtoupper((string) $label->getPickupPointCountryCode()).'-'.$label->getPickupPointId());
        $collection = $shipment->appendChild($document->createElement('CollectionMode'));
        $collection->setAttribute('Mode', 'CCC');
        $collection->setAttribute('Location', 'Auto');
        $parcel = $shipment->appendChild($document->createElement('Parcels'))->appendChild($document->createElement('Parcel'));
        $this->elements($document, $parcel, ['Content' => 'Commande Tinned']);
        foreach (['Weight' => [$label->getWeightGrams(), 'gr'], 'Length' => [1, 'cm'], 'Width' => [31, 'cm'], 'Depth' => [41, 'cm']] as $name => [$value, $unit]) {
            $node = $parcel->appendChild($document->createElement($name));
            $node->setAttribute('Value', (string) $value);
            $node->setAttribute('Unit', $unit);
        }
        $sender = $shipment->appendChild($document->createElement('Sender'))->appendChild($document->createElement('Address'));
        $this->elements($document, $sender, [
            'Title' => $this->sender->name,
            'Streetname' => $this->sender->street,
            'HouseNo' => $this->sender->streetNumber,
            'PostCode' => $this->sender->postalCode,
            'City' => mb_substr($this->sender->city, 0, 32),
            'CountryCode' => strtoupper($this->sender->countryCode),
            'MobileNo' => $this->sender->phone,
            'Email' => $this->sender->email,
        ]);
        $recipient = $shipment->appendChild($document->createElement('Recipient'))->appendChild($document->createElement('Address'));
        $this->elements($document, $recipient, [
            'Firstname' => mb_substr($address->getFirstName(), 0, 15),
            'Lastname' => mb_substr($address->getLastName(), 0, 15),
            'Streetname' => $address->getStreet(),
            'HouseNo' => '',
            'PostCode' => str_replace(' ', '', $address->getPostalCode()),
            'City' => $address->getCity(),
            'CountryCode' => strtoupper($address->getCountryCode()),
            'PhoneNo' => $address->getPhone() ?? $user->getPhone() ?? '',
            'Email' => $user->getEmail() ?? '',
        ]);

        $xml = $this->httpClient->request('POST', $this->apiUrl, [
            'headers' => ['Accept' => 'application/xml', 'Content-Type' => 'text/xml'],
            'body' => $document->saveXML(),
        ])->getContent();
        $response = new \SimpleXMLElement($xml);
        $status = $response->xpath('//*[local-name()="Status"]')[0] ?? null;
        if ($status && (int) $status['Code'] !== 0) {
            throw new ShippingLabelException('Mondial Relay a refuse la generation: '.(string) $status['Message']);
        }

        $outputs = $response->xpath('//*[local-name()="Output"]');
        $labelUrl = isset($outputs[0]) ? trim((string) $outputs[0]) : '';
        if ($labelUrl === '') {
            throw new ShippingLabelException('Mondial Relay n a retourne aucune etiquette.');
        }
        $tracking = null;
        foreach ($response->xpath('//*[local-name()="LabelValues"]') ?: [] as $value) {
            if ((string) $value['Key'] === 'MR.Expedition.CodeBarres') {
                $tracking = (string) $value['Value'];
                break;
            }
        }

        return new CarrierLabelResult(
            $this->httpClient->request('GET', $labelUrl)->getContent(),
            $tracking,
            $tracking ? sprintf('https://www.mondialrelay.fr/suivi-de-colis?codeMarque=%s&numeroExpedition=%s&language=fr', rawurlencode($this->brandId), rawurlencode($tracking)) : null,
        );
    }

    private function requireConfigured(ShippingLabel $label): void
    {
        if ($this->apiUrl === '' || $this->brandId === '' || $this->login === '' || $this->password === '') {
            throw new ShippingLabelException('Mondial Relay n est pas configure pour Tinned.');
        }
        foreach ([$label->getPickupPointId(), $label->getPickupPointName(), $label->getPickupPointStreet(), $label->getPickupPointPostalCode(), $label->getPickupPointCity(), $label->getPickupPointCountryCode()] as $value) {
            if (!$value) {
                throw new ShippingLabelException('Renseignez le point relais Mondial Relay avant de generer l etiquette.');
            }
        }
    }

    /** @param array<string, string> $values */
    private function elements(\DOMDocument $document, \DOMNode $parent, array $values): void
    {
        foreach ($values as $name => $value) {
            $node = $parent->appendChild($document->createElement($name));
            $node->appendChild($document->createTextNode($value));
        }
    }
}
