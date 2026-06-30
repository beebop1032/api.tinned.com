<?php

namespace App\ApiResource\Dashboard;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Model\Dashboard\DashboardStats;
use App\Provider\Dashboard\DashboardStatsProvider;

#[ApiResource(
    shortName: 'DashboardStats',
    operations: [
        new Get(
            uriTemplate: '/dashboard/stats',
            normalizationContext: ['groups' => ['dashboard:read']],
            output: DashboardStats::class,
            provider: DashboardStatsProvider::class,
            security: "is_granted('ROLE_USER')",
            read: true,
        ),
    ],
)]
final class DashboardStatsResource {}
