<?php

namespace App\ApiResource\Shopping;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Model\Shopping\CheckoutResponse;
use App\Provider\Shopping\MyOrdersProvider;

#[ApiResource(
    shortName: 'MyOrder',
    operations: [
        new GetCollection(
            uriTemplate: '/my_orders',
            security: "is_granted('ROLE_USER')",
            output: CheckoutResponse::class,
            provider: MyOrdersProvider::class,
        ),
    ],
)]
final class MyOrdersResource {}
