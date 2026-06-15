<?php

namespace App\ApiResource\User;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use App\Processor\User\DeleteAccountProcessor;

#[ApiResource(
    shortName: 'DeleteAccount',
    operations: [
        new Delete(
            uriTemplate: '/me',
            security: "is_granted('ROLE_USER')",
            processor: DeleteAccountProcessor::class,
            status: 204,
            read: false,
            output: false,
        ),
    ],
    output: false,
)]
class DeleteAccountResource {}
