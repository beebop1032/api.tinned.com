<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\BlockCatalogProvider;

#[ApiResource(
    shortName: 'BlockCatalog',
    operations: [
        new Get(
            uriTemplate: '/block_catalog',
            provider: BlockCatalogProvider::class,
        ),
    ],
)]
final class BlockCatalogResource
{
    public function __construct(
        /** @var array<string, list<string>> type => champs requis */
        public array $types = [],
        /** @var list<string> */
        public array $collectionSources = [],
    ) {
    }
}
