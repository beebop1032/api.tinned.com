<?php

namespace App\ApiResource\App;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Model\App\VersionResponse;
use App\Provider\App\VersionProvider;

#[ApiResource(
    shortName: 'Version',
    operations: [
        new Get(
            uriTemplate: '/version',
            output: VersionResponse::class,
            provider: VersionProvider::class,
        )
    ],
    output: false,
)]
class VersionResource {}
