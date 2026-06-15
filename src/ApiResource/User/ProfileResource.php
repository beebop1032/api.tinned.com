<?php

namespace App\ApiResource\User;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Put;
use App\Model\User\MeResponse;
use App\Model\User\UpdateProfile;
use App\Processor\User\UpdateProfileProcessor;

#[ApiResource(
    shortName: 'MyProfile',
    operations: [
        new Put(
            uriTemplate: '/my_profile',
            security: "is_granted('ROLE_USER')",
            input: UpdateProfile::class,
            output: MeResponse::class,
            processor: UpdateProfileProcessor::class,
        ),
    ],
)]
final class ProfileResource {}
