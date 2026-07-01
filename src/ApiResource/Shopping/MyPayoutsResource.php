<?php

namespace App\ApiResource\Shopping;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Shopping\PayoutLedgerEntry;
use App\Provider\Shopping\MyPayoutsProvider;

#[ApiResource(
    shortName: 'MyPayouts',
    class: PayoutLedgerEntry::class,
    operations: [
        new GetCollection(
            uriTemplate: '/my_payouts',
            normalizationContext: ['groups' => ['payout:read']],
            provider: MyPayoutsProvider::class,
            security: "is_granted('ROLE_USER')",
        ),
    ],
)]
class MyPayoutsResource {}
