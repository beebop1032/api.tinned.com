<?php

namespace App\Processor\Review;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Product\Product;
use App\Entity\Review\Review;
use App\Service\Review\ProductRatingUpdater;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Admin moderation of reviews (approve / reject / reply, or delete). After every change
 * the product's rating aggregate is recomputed so the public stars stay accurate.
 */
class ReviewModerationProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductRatingUpdater $ratingUpdater,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Review
    {
        if (!$data instanceof Review) {
            throw new \InvalidArgumentException('Expected a Review.');
        }

        $product = $data->getProduct();

        if ($operation instanceof Delete) {
            $this->em->remove($data);
            $this->em->flush();
            if ($product instanceof Product) {
                $this->ratingUpdater->recompute($product);
            }

            return null;
        }

        $this->em->persist($data);
        $this->em->flush();
        if ($product instanceof Product) {
            $this->ratingUpdater->recompute($product);
        }

        return $data;
    }
}
