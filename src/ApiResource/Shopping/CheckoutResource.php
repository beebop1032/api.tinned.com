<?php

namespace App\ApiResource\Shopping;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Model\Shopping\CheckoutRequest;
use App\Model\Shopping\CheckoutResponse;
use App\Processor\Shopping\CheckoutProcessor;

#[ApiResource(
    shortName: 'Checkout',
    operations: [
        new Post(
            uriTemplate: '/checkout',
            security: "is_granted('ROLE_USER')",
            input: CheckoutRequest::class,
            output: CheckoutResponse::class,
            processor: CheckoutProcessor::class,
        ),
    ],
)]
final class CheckoutResource {}
