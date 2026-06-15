<?php

namespace App\Service\Shipping;

use App\Entity\Delivery\DeliveryMethod;
use App\Entity\Delivery\ShippingLabel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class ShippingLabelGenerator
{
    /** @param iterable<CarrierLabelAdapterInterface> $adapters */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[TaggedIterator('app.shipping_label_adapter')]
        private readonly iterable $adapters,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire('%app.public_base_url%')]
        private readonly string $publicBaseUrl,
    ) {
    }

    public function generate(ShippingLabel $label): ShippingLabel
    {
        try {
            $storeOrder = $label->getStoreOrder();
            $address = $storeOrder?->getCustomerOrder()?->getShippingAddress();
            if (!$storeOrder || !$address || !$label->getId()) {
                throw new ShippingLabelException('La commande ne contient pas une adresse de livraison exploitable.');
            }

            $deliveryMethod = $this->entityManager->getRepository(DeliveryMethod::class)->findOneBy([
                'code' => $label->getCarrierCode(),
                'countryCode' => strtoupper($address->getCountryCode()),
            ]);
            if (!$deliveryMethod instanceof DeliveryMethod) {
                throw new ShippingLabelException('La methode de livraison de cette commande est introuvable.');
            }

            $adapter = $this->adapterFor($deliveryMethod->getProvider());
            $result = $adapter->generate($label, $deliveryMethod);
            if ($result->pdfContent === '') {
                throw new ShippingLabelException('Le transporteur n a retourne aucun PDF.');
            }

            $directory = $this->projectDir.'/public/uploads/shipping-labels';
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new ShippingLabelException('Impossible de preparer le dossier des etiquettes.');
            }

            $filename = sprintf('label-%d-%s.pdf', $label->getId(), bin2hex(random_bytes(5)));
            if (file_put_contents($directory.'/'.$filename, $result->pdfContent) === false) {
                throw new ShippingLabelException('Impossible de sauvegarder le PDF de l etiquette.');
            }

            $label
                ->setLabelUrl(rtrim($this->publicBaseUrl, '/').'/uploads/shipping-labels/'.$filename)
                ->setTrackingNumber($result->trackingNumber)
                ->setTrackingUrl($result->trackingUrl)
                ->setErrorMessage(null)
                ->setStatus(ShippingLabel::STATUS_READY);
        } catch (\Throwable $exception) {
            $label
                ->setErrorMessage($exception->getMessage())
                ->setStatus(ShippingLabel::STATUS_ERROR);
        }

        $this->entityManager->flush();

        return $label;
    }

    private function adapterFor(string $provider): CarrierLabelAdapterInterface
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($provider)) {
                return $adapter;
            }
        }

        throw new ShippingLabelException(sprintf('Aucun generateur d etiquette n est configure pour %s.', $provider));
    }
}
