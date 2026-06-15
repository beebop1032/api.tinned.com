<?php
namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Components;
use ApiPlatform\OpenApi\Model\SecurityScheme;
use ApiPlatform\OpenApi\OpenApi;

final class SecurityDecorator implements OpenApiFactoryInterface
{
    public function __construct(private OpenApiFactoryInterface $decorated) {}

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        $components = $openApi->getComponents() ?? new Components();
        $schemes = $components->getSecuritySchemes() ?? new \ArrayObject();

        // JWT
        $schemes['bearerAuth'] = new SecurityScheme(
            type: 'http',
            description: 'Use POST /api/login to get a token, then click Authorize.',
            scheme: 'bearer',
            bearerFormat: 'JWT'
        );

        // APP KEY
        $schemes['appKeyAuth'] = new SecurityScheme(
            type: 'apiKey',
            description: 'Mobile app key header.',
            name: 'X-APP-API-KEY',
            in: 'header'
        );

        $components = $components->withSecuritySchemes($schemes);
        $openApi = $openApi->withComponents($components);

        // Global security: OR between the objects
        // (JWT) OR (APP KEY)
        return $openApi->withSecurity([
            ['bearerAuth' => []],
            ['appKeyAuth' => []],
        ]);
    }
}
