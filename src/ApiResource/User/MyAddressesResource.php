<?php

namespace App\ApiResource\User;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Model\User\AddressInput;
use App\Model\User\AddressResponse;
use App\Processor\User\CreateMyAddressProcessor;
use App\Processor\User\UpdateMyAddressProcessor;
use App\Provider\User\MyAddressesProvider;

#[ApiResource(
    shortName: 'MyAddress',
    operations: [
        new GetCollection(
            uriTemplate: '/my_addresses',
            security: "is_granted('ROLE_USER')",
            output: AddressResponse::class,
            provider: MyAddressesProvider::class,
        ),
        new Post(
            uriTemplate: '/my_addresses',
            security: "is_granted('ROLE_USER')",
            input: AddressInput::class,
            output: AddressResponse::class,
            processor: CreateMyAddressProcessor::class,
        ),
        new Put(
            uriTemplate: '/my_addresses/{id}',
            security: "is_granted('ROLE_USER')",
            read: false,
            input: AddressInput::class,
            output: AddressResponse::class,
            processor: UpdateMyAddressProcessor::class,
        ),
    ],
)]
final class MyAddressesResource {}
