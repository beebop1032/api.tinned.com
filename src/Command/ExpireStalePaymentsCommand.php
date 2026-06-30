<?php

namespace App\Command;

use App\Entity\Shopping\CustomerOrder;
use App\Entity\Shopping\StoreOrder;
use App\Service\Shopping\OrderInventoryReleaser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Cancels orders that have been awaiting payment for too long and gives their reserved
 * stock + coupon usage back, so an abandoned checkout never holds inventory hostage.
 * Meant to run on a schedule (e.g. every 5 minutes).
 */
#[AsCommand(
    name: 'app:orders:expire-stale-payments',
    description: 'Cancel and restock orders stuck in pending_payment beyond the grace period.',
)]
class ExpireStalePaymentsCommand extends Command
{
    private const GRACE_MINUTES = 30;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OrderInventoryReleaser $inventoryReleaser,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $cutoff = new \DateTimeImmutable(sprintf('-%d minutes', self::GRACE_MINUTES));

        /** @var list<CustomerOrder> $stale */
        $stale = $this->em->getRepository(CustomerOrder::class)
            ->createQueryBuilder('o')
            ->where('o.status = :pending')
            ->andWhere('o.createdAt < :cutoff')
            ->setParameter('pending', CustomerOrder::STATUS_PENDING_PAYMENT)
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getResult();

        foreach ($stale as $order) {
            $order->setStatus(CustomerOrder::STATUS_CANCELLED);
            $order->setPaymentStatus('expired');
            foreach ($order->getStoreOrders() as $storeOrder) {
                if (!in_array($storeOrder->getStatus(), [StoreOrder::STATUS_SHIPPED, StoreOrder::STATUS_COMPLETED], true)) {
                    $storeOrder->setStatus(StoreOrder::STATUS_CANCELLED);
                }
            }
            $this->inventoryReleaser->release($order);
        }

        $this->em->flush();

        $io->success(sprintf('%d stale order(s) expired and restocked.', count($stale)));

        return Command::SUCCESS;
    }
}
