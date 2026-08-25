<?php

namespace App\EventListener;

use App\Entity\Product\Product;
use App\Service\Marketing\LaunchNotifier;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;

/**
 * Déclenche automatiquement l'email « c'est en ligne » aux inscrits « prévenez-moi »
 * dès qu'un produit passe de coming_soon / preorder à available.
 *
 * onFlush collecte les transitions (accès au changeset) ; postFlush envoie, après commit.
 * L'envoi (LaunchNotifier) est idempotent (notifiedAt), donc un ré-enregistrement sans
 * changement réel de disponibilité ne renvoie rien.
 *
 * Le retour en stock n'est PAS auto-déclenché ici (détection cross-variantes fragile) :
 * il passe par la commande console / l'endpoint admin, où le vendeur maîtrise le timing.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
class ProductAvailabilityListener
{
    private const PRELAUNCH = ['coming_soon', 'preorder'];

    /** @var list<Product> */
    private array $justLaunched = [];

    private bool $running = false;

    public function __construct(
        private readonly LaunchNotifier $launchNotifier,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof Product) {
                continue;
            }

            $changeSet = $uow->getEntityChangeSet($entity);
            if (!isset($changeSet['availability'])) {
                continue;
            }

            [$old, $new] = $changeSet['availability'];
            if ($new === 'available' && \in_array($old, self::PRELAUNCH, true)) {
                $this->justLaunched[] = $entity;
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        // Garde anti-réentrance : le flush du notifier redéclenche postFlush.
        if ($this->running || $this->justLaunched === []) {
            return;
        }

        $this->running = true;
        try {
            $products = $this->justLaunched;
            $this->justLaunched = [];
            foreach ($products as $product) {
                $this->launchNotifier->notifyProductLive($product);
            }
        } finally {
            $this->running = false;
        }
    }
}
