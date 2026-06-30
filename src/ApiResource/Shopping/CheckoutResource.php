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
            // Public: guest checkout is allowed. The processor resolves a logged-in user
            // or creates/reuses a guest account from the provided email.
            uriTemplate: '/checkout',
            input: CheckoutRequest::class,
            output: CheckoutResponse::class,
            processor: CheckoutProcessor::class,
        ),
    ],
)]
final class CheckoutResource {}
