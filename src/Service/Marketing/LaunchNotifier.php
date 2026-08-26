<?php

namespace App\Service\Marketing;

use App\Entity\Marketing\Subscription;
use App\Entity\Product\Product;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Prévient les inscrits « prévenez-moi » d'un produit quand il devient disponible
 * (mise en ligne) ou de retour en stock.
 *
 * Idempotent : chaque inscription confirmée n'est notifiée qu'une seule fois
 * (champ notifiedAt), conformément à la promesse UI « un seul email, rien d'autre ».
 * Résilient : un échec d'envoi n'empêche jamais de traiter les autres inscrits.
 */
class LaunchNotifier
{
    // Garde-fou : au-delà, on s'arrête et on loggue plutôt que de bloquer une requête
    // sur un envoi synchrone massif. Utiliser la commande console pour les grosses listes.
    private const MAX_PER_RUN = 500;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ResendMailer $mailer,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(APP_FRONT_URL)%')]
        private readonly string $frontUrl = 'http://localhost:4001',
    ) {
    }

    public function notifyProductLive(Product $product): int
    {
        return $this->notify(
            $product,
            fn (Subscription $s, string $name, string $url) => $this->mailer->sendLaunchLive($s, $name, $url),
        );
    }

    public function notifyBackInStock(Product $product): int
    {
        return $this->notify(
            $product,
            fn (Subscription $s, string $name, string $url) => $this->mailer->sendBackInStock($s, $name, $url),
        );
    }

    /**
     * @param callable(Subscription, string, string): bool $send
     */
    private function notify(Product $product, callable $send): int
    {
        $pending = $this->em->getRepository(Subscription::class)->findBy([
            'targetType' => Subscription::TARGET_PRODUCT,
            'product' => $product,
            'status' => Subscription::STATUS_CONFIRMED,
            'notifiedAt' => null,
        ], ['id' => 'ASC'], self::MAX_PER_RUN + 1);

        if (\count($pending) > self::MAX_PER_RUN) {
            $this->logger->warning('LaunchNotifier: plus de MAX_PER_RUN inscrits, envoi tronqué pour cette exécution.', [
                'product' => $product->getId(),
                'max' => self::MAX_PER_RUN,
            ]);
            $pending = \array_slice($pending, 0, self::MAX_PER_RUN);
        }

        if ($pending === []) {
            return 0;
        }

        $name = $product->getName();
        $url = $this->buildProductUrl($product);
        $now = new \DateTimeImmutable();
        $sent = 0;

        foreach ($pending as $subscription) {
            // Respecte l'opt-out global : un désabonné marketing ne reçoit rien (mais
            // n'est pas marqué notifié, au cas où il se réabonnerait plus tard).
            $subUser = $subscription->getUser();
            if ($subUser !== null && !$subUser->hasMarketingConsent()) {
                continue;
            }

            try {
                $send($subscription, $name, $url);
            } catch (\Throwable $e) {
                // On loggue et on continue : un email raté ne doit pas bloquer les suivants.
                $this->logger->error('LaunchNotifier: échec d\'envoi pour un inscrit.', [
                    'subscription' => $subscription->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
            // Marqué notifié même si l'envoi a échoué : ResendMailer loggue déjà les échecs,
            // et on ne veut jamais spammer en réessayant en boucle sur une adresse morte.
            $subscription->setNotifiedAt($now);
            ++$sent;
        }

        $this->em->flush();

        $this->logger->info('LaunchNotifier: inscrits notifiés.', [
            'product' => $product->getId(),
            'count' => $sent,
        ]);

        return $sent;
    }

    /** Réplique front/lib/commerce.ts productHref : /store-box/{box}/{produit}/{variante}. */
    private function buildProductUrl(Product $product): string
    {
        $storeSlug = $product->getStoreBox()?->getSlug() ?? 'store';

        $variantSku = 'variant';
        $firstSku = null;
        foreach ($product->getVariants() as $variant) {
            $firstSku ??= $variant->getSku();
            if ($variant->getStock() > 0) {
                $variantSku = $variant->getSku();
                break;
            }
        }
        if ($variantSku === 'variant' && $firstSku !== null) {
            $variantSku = $firstSku;
        }

        return sprintf(
            '%s/store-box/%s/%s/%s',
            rtrim($this->frontUrl, '/'),
            rawurlencode($storeSlug),
            rawurlencode($product->getSlug()),
            rawurlencode(strtolower($variantSku)),
        );
    }
}
