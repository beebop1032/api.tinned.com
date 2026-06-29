<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BlockCatalogResource;
use App\Service\Content\BlockCatalog;

final class BlockCatalogProvider implements ProviderInterface
{
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): BlockCatalogResource
    {
        return new BlockCatalogResource(
            types: BlockCatalog::TYPES,
            collectionSources: BlockCatalog::COLLECTION_SOURCES,
        );
    }
}
