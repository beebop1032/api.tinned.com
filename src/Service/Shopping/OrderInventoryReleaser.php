<?php

namespace App\Service\Shopping;

use App\Entity\Product\StockMovement;
use App\Entity\Shopping\Coupon;
use App\Entity\Shopping\CustomerOrder;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gives back the stock and coupon usage that a checkout reserved up-front, when the
 * order ends up unpaid (payment failed / expired / cancelled). Idempotent: guarded by
 * CustomerOrder::inventoryReleased so a replayed Mollie webhook never double-credits.
 *
 * Does not flush — the caller owns the transaction boundary.
 */
readonly class OrderInventoryReleaser
{
    public function __construct(private EntityManagerInterface $em) {}

    public function release(CustomerOrder $order): void
    {
        if ($order->isInventoryReleased()) {
            return;
        }

        foreach ($order->getLines() as $line) {
            $variant = $line->getVariant();
            $reserved = $line->getStockReserved();
            if (!$variant || $reserved <= 0) {
                continue;
            }

            $variant->setStock($variant->getStock() + $reserved);
            $this->em->persist(
                (new StockMovement())
                    ->setVariant($variant)
                    ->setDelta($reserved)
                    ->setReason('return')
                    ->setResultingStock($variant->getStock())
                    ->setNote(sprintf('Restitution commande %s', $order->getReference()))
            );
        }

        if ($order->getCouponCode()) {
            $coupon = $this->em->getRepository(Coupon::class)->findOneBy(['code' => $order->getCouponCode()]);
            $coupon?->decrementUsedCount();
        }

        $order->setInventoryReleased(true);
    }
}
