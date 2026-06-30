<?php

namespace App\Provider\Dashboard;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Product\ProductVariant;
use App\Entity\Shopping\OrderLine;
use App\Entity\Shopping\StoreOrder;
use App\Entity\User;
use App\Model\Dashboard\DashboardStats;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Builds vendor dashboard stats, scoped to the store boxes owned by the current user.
 * "Paid" store orders are those that have moved past the open state.
 *
 * @implements ProviderInterface<DashboardStats>
 */
class DashboardStatsProvider implements ProviderInterface
{
    private const PAID_STATUSES = [
        StoreOrder::STATUS_WAITING_STORE,
        StoreOrder::STATUS_PREPARING,
        StoreOrder::STATUS_SHIPPED,
        StoreOrder::STATUS_COMPLETED,
    ];
    private const LOW_STOCK_THRESHOLD = 3;

    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): DashboardStats
    {
        $stats = new DashboardStats();
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $stats;
        }

        /** @var list<StoreOrder> $paidOrders */
        $paidOrders = $this->em->getRepository(StoreOrder::class)
            ->createQueryBuilder('so')
            ->join('so.storeBox', 'sb')
            ->where('sb.owner = :user')
            ->andWhere('so.status IN (:paid)')
            ->setParameter('user', $user)
            ->setParameter('paid', self::PAID_STATUSES)
            ->getQuery()
            ->getResult();

        $byDay = [];
        foreach ($paidOrders as $storeOrder) {
            $amount = $storeOrder->getSubtotalCents() + $storeOrder->getShippingCents();
            $stats->revenueCents += $amount;
            $stats->paidOrderCount += 1;
            if (in_array($storeOrder->getStatus(), [StoreOrder::STATUS_WAITING_STORE, StoreOrder::STATUS_PREPARING], true)) {
                $stats->toPrepareCount += 1;
            }
            $day = $storeOrder->getCreatedAt()->format('Y-m-d');
            $byDay[$day] = ($byDay[$day] ?? 0) + $amount;
        }

        $stats->averageOrderValueCents = $stats->paidOrderCount > 0
            ? intdiv($stats->revenueCents, $stats->paidOrderCount)
            : 0;

        // Last 30 days, oldest first, with explicit zero-days so the chart is continuous.
        $today = new \DateTimeImmutable('today');
        for ($i = 29; $i >= 0; $i--) {
            $date = $today->sub(new \DateInterval("P{$i}D"))->format('Y-m-d');
            $stats->revenueByDay[] = ['date' => $date, 'revenueCents' => $byDay[$date] ?? 0];
        }

        $topProducts = $this->em->getRepository(OrderLine::class)
            ->createQueryBuilder('ol')
            ->select('ol.productNameSnapshot AS name', 'SUM(ol.quantity) AS qty', 'SUM(ol.unitPriceCentsSnapshot * ol.quantity) AS rev')
            ->join('ol.storeBox', 'sb')
            ->join('ol.storeOrder', 'so')
            ->where('sb.owner = :user')
            ->andWhere('so.status IN (:paid)')
            ->setParameter('user', $user)
            ->setParameter('paid', self::PAID_STATUSES)
            ->groupBy('ol.productNameSnapshot')
            ->orderBy('qty', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getArrayResult();

        $stats->topProducts = array_map(static fn (array $row): array => [
            'name' => (string) $row['name'],
            'quantity' => (int) $row['qty'],
            'revenueCents' => (int) $row['rev'],
        ], $topProducts);

        $lowStock = $this->em->getRepository(ProductVariant::class)
            ->createQueryBuilder('v')
            ->select('v.sku AS sku', 'p.name AS productName', 'v.stock AS stock')
            ->join('v.product', 'p')
            ->join('p.storeBox', 'sb')
            ->where('sb.owner = :user')
            ->andWhere('v.active = true')
            ->andWhere('v.stock <= :threshold')
            ->setParameter('user', $user)
            ->setParameter('threshold', self::LOW_STOCK_THRESHOLD)
            ->orderBy('v.stock', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getArrayResult();

        $stats->lowStock = array_map(static fn (array $row): array => [
            'sku' => (string) $row['sku'],
            'productName' => (string) $row['productName'],
            'stock' => (int) $row['stock'],
        ], $lowStock);

        return $stats;
    }
}
