<?php

namespace App\Processor\Product;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Product\StockMovement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Applies a manually created stock movement to its variant: the variant stock
 * is shifted by `delta` (clamped at 0, never negative), the resulting stock is
 * snapshotted on the movement, then both are persisted and flushed.
 */
class StockMovementProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): StockMovement
    {
        if (!$data instanceof StockMovement) {
            throw new \InvalidArgumentException('Expected a StockMovement.');
        }

        $variant = $data->getVariant();
        if ($variant === null) {
            $this->fail('variant', 'A stock movement requires a variant.');
        }

        if (!in_array($data->getReason(), StockMovement::REASONS, true)) {
            $this->fail('reason', 'Unsupported stock movement reason.');
        }

        $newStock = max(0, $variant->getStock() + $data->getDelta());
        $variant->setStock($newStock);
        $data->setResultingStock($newStock);

        $this->em->persist($data);
        $this->em->flush();

        return $data;
    }

    private function fail(string $path, string $message): never
    {
        throw new ValidationException(new ConstraintViolationList([
            new ConstraintViolation($message, null, [], null, $path, null),
        ]));
    }
}
