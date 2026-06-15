<?php

namespace App\Provider\App;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Model\App\VersionResponse;

readonly class VersionProvider implements ProviderInterface
{
    public function __construct(private string $appVersion) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): VersionResponse
    {
        return new VersionResponse($this->appVersion);
    }
}
