<?php

namespace App\ApiResource\User;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Model\User\OAuthLogin;
use App\Model\User\RegisterUserResponse;
use App\Processor\User\OAuthLoginProcessor;

#[ApiResource(
    shortName: "OAuthLogin",
    operations: [
        new Post(
            uriTemplate: "/oauth/login",
            security: "is_granted('PUBLIC_ACCESS')",
            input: OAuthLogin::class,
            output: RegisterUserResponse::class,
            processor: OAuthLoginProcessor::class
        )
    ],
    output: false
)]
class OAuthLoginResource {}
