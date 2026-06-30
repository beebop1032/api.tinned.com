<?php

namespace App\Model\Dashboard;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Aggregated selling stats for the current vendor, scoped to the store boxes they own.
 * All monetary fields are in cents. Read-only; built by DashboardStatsProvider.
 */
class DashboardStats
{
    #[Groups(['dashboard:read'])]
    public int $revenueCents = 0;

    #[Groups(['dashboard:read'])]
    public int $paidOrderCount = 0;

    #[Groups(['dashboard:read'])]
    public int $averageOrderValueCents = 0;

    #[Groups(['dashboard:read'])]
    public int $toPrepareCount = 0;

    /** @var list<array{name: string, quantity: int, revenueCents: int}> */
    #[Groups(['dashboard:read'])]
    public array $topProducts = [];

    /** @var list<array{sku: string, productName: string, stock: int}> */
    #[Groups(['dashboard:read'])]
    public array $lowStock = [];

    /** @var list<array{date: string, revenueCents: int}> */
    #[Groups(['dashboard:read'])]
    public array $revenueByDay = [];
}
