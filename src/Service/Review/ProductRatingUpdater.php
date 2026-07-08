<?php

namespace App\Service\Review;

use App\Entity\Product\Product;
use App\Entity\Review\Review;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Keeps the denormalized rating aggregate on Product (ratingCount / ratingSum) in sync
 * with the approved reviews. Recomputed from scratch on every review write so it is always
 * correct regardless of the transition (create, approve, reject, delete).
 */
final class ProductRatingUpdater
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function recompute(Product $product): void
    {
        /** @var array{cnt: int|string, total: int|string|null} $row */
        $row = $this->em->getRepository(Review::class)
            ->createQueryBuilder('r')
            ->select('COUNT(r.id) AS cnt', 'COALESCE(SUM(r.rating), 0) AS total')
            ->where('r.product = :product')
            ->andWhere('r.status = :status')
            ->setParameter('product', $product)
            ->setParameter('status', Review::STATUS_APPROVED)
            ->getQuery()
            ->getSingleResult();

        $product->setRatingCount((int) $row['cnt']);
        $product->setRatingSum((int) $row['total']);
        $this->em->flush();
    }
}
