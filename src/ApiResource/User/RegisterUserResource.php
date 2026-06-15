<?php

namespace App\ApiResource\User;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Model\User\RegisterUser;
use App\Model\User\RegisterUserResponse;
use App\Processor\User\RegisterUserProcessor;

#[ApiResource(
    shortName: "Register",
    operations: [
        new Post(
            uriTemplate: "/register",
            security: "is_granted('PUBLIC_ACCESS')",
            input: RegisterUser::class,
            output: RegisterUserResponse::class,
            processor: RegisterUserProcessor::class
        )
    ],
    output: false
)]
class RegisterUserResource {}
