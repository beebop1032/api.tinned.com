<?php
namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Components;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
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
        $openApi = $openApi->withSecurity([
            ['bearerAuth' => []],
            ['appKeyAuth' => []],
        ]);

        // Documente la route custom d'upload média (AdminMediaController) qui, étant une
        // route Symfony et non une ressource API Platform, n'apparaît pas seule dans la doc.
        $openApi->getPaths()->addPath('/api/admin/media', new PathItem(post: $this->mediaUploadOperation()));

        return $openApi;
    }

    private function mediaUploadOperation(): Operation
    {
        $requestBody = new RequestBody(
            description: 'Fichier image à uploader.',
            content: new \ArrayObject([
                'multipart/form-data' => new MediaType(new \ArrayObject([
                    'type' => 'object',
                    'properties' => [
                        'file' => [
                            'type' => 'string',
                            'format' => 'binary',
                            'description' => 'Image (gif, jpg, png, svg, webp), 3 Mo max.',
                        ],
                    ],
                    'required' => ['file'],
                ])),
            ]),
            required: true,
        );

        $okContent = new \ArrayObject([
            'application/json' => new MediaType(new \ArrayObject([
                'type' => 'object',
                'properties' => [
                    'path' => ['type' => 'string', 'example' => '/uploads/media/photo-ab12cd34ef.png'],
                    'url' => ['type' => 'string', 'example' => 'https://api.tinned.com/uploads/media/photo-ab12cd34ef.png'],
                ],
            ])),
        ]);

        return new Operation(
            operationId: 'api_admin_media_upload',
            tags: ['Media'],
            responses: [
                '200' => new Response(description: 'Image uploadée.', content: $okContent),
                '400' => new Response(description: 'Fichier manquant, trop lourd (> 3 Mo) ou format non supporté.'),
                '403' => new Response(description: 'Réservé aux administrateurs (ROLE_ADMIN).'),
            ],
            summary: 'Upload d\'une image (backoffice)',
            description: 'Upload multipart d\'une image dans /uploads/media. Réservé à ROLE_ADMIN. Renvoie le chemin et l\'URL publique du fichier.',
            requestBody: $requestBody,
            security: [['bearerAuth' => []]],
        );
    }
}
