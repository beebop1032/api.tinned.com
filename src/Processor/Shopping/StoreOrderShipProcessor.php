<?php

namespace App\Processor\Shopping;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Shopping\StoreOrder;
use App\Service\Shopping\OrderMailer;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Handles a vendor PATCH on their own store order (status transition + tracking).
 * When the order is first marked shipped, it stamps shippedAt once and emails the
 * buyer the tracking link. Ownership is already enforced by the operation security.
 *
 * @implements ProcessorInterface<StoreOrder>
 */
readonly class StoreOrderShipProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private OrderMailer $orderMailer,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): StoreOrder
    {
        if (!$data instanceof StoreOrder) {
            throw new \InvalidArgumentException('Expected a StoreOrder.');
        }

        $justShipped = $data->getStatus() === StoreOrder::STATUS_SHIPPED && $data->getShippedAt() === null;
        if ($justShipped) {
            $data->setShippedAt(new \DateTimeImmutable());
        }

        $this->em->flush();

        if ($justShipped) {
            $this->orderMailer->sendShipped($data, $data->getTrackingUrl());
        }

        return $data;
    }
}
