<?php

namespace App\ApiResource\User;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Model\User\MeResponse;
use App\Processor\User\MeProcessor;

#[ApiResource(
    shortName: "Me",
    operations: [
        new Post(
            uriTemplate: "/me",
            output: MeResponse::class,
            processor: MeProcessor::class,
        )
    ],
    output: false
)]
class MeResource {}
