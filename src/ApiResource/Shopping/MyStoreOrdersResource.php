<?php

namespace App\ApiResource\Shopping;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\Entity\Shopping\StoreOrder;
use App\Processor\Shopping\StoreOrderShipProcessor;
use App\Provider\Shopping\MyStoreOrdersProvider;

#[ApiResource(
    shortName: 'MyStoreOrders',
    class: StoreOrder::class,
    operations: [
        new GetCollection(
            uriTemplate: '/my_store_orders',
            normalizationContext: ['groups' => ['shipping:read']],
            provider: MyStoreOrdersProvider::class,
            security: "is_granted('ROLE_USER')",
        ),
        new Patch(
            uriTemplate: '/my_store_orders/{id}',
            normalizationContext: ['groups' => ['shipping:read']],
            denormalizationContext: ['groups' => ['shipping:write']],
            provider: MyStoreOrdersProvider::class,
            processor: StoreOrderShipProcessor::class,
            security: "is_granted('ROLE_USER') and (object.getStoreBox() === null or object.getStoreBox().getOwner() === null or object.getStoreBox().getOwner().getId() === user.getId())",
        ),
    ],
)]
class MyStoreOrdersResource {}
