<?php

namespace App\ApiResource\User;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Model\User\PasswordResetResponse;
use App\Model\User\RequestPasswordReset;
use App\Model\User\ResetPassword;
use App\Processor\User\RequestPasswordResetProcessor;
use App\Processor\User\ResetPasswordProcessor;

#[ApiResource(
    shortName: 'PasswordReset',
    operations: [
        new Post(
            uriTemplate: '/request_password_reset',
            security: "is_granted('PUBLIC_ACCESS')",
            input: RequestPasswordReset::class,
            output: PasswordResetResponse::class,
            processor: RequestPasswordResetProcessor::class
        ),
        new Post(
            uriTemplate: '/reset_password',
            security: "is_granted('PUBLIC_ACCESS')",
            input: ResetPassword::class,
            output: PasswordResetResponse::class,
            processor: ResetPasswordProcessor::class
        )
    ],
    output: false
)]
class PasswordResetResource
{
}
