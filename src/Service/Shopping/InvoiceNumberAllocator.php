<?php

namespace App\Service\Shopping;

use App\Entity\Shopping\InvoiceCounter;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Allocates the next legal, sequential invoice number for the current year, e.g.
 * "2026-000123". Increments the per-year counter under a pessimistic lock in its own
 * transaction so concurrent payments never collide or reuse a number.
 */
readonly class InvoiceNumberAllocator
{
    public function __construct(private EntityManagerInterface $em) {}

    public function allocate(\DateTimeImmutable $now = new \DateTimeImmutable()): string
    {
        $year = (int) $now->format('Y');
        $repository = $this->em->getRepository(InvoiceCounter::class);

        $this->em->beginTransaction();
        try {
            $counter = $repository->findOneBy(['year' => $year]);
            if (!$counter) {
                $counter = (new InvoiceCounter())->setYear($year);
                $this->em->persist($counter);
                $this->em->flush();
            }

            $this->em->lock($counter, LockMode::PESSIMISTIC_WRITE);
            $counter->setLastNumber($counter->getLastNumber() + 1);
            $number = sprintf('%d-%06d', $year, $counter->getLastNumber());
            $this->em->flush();
            $this->em->commit();

            return $number;
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }
    }
}
