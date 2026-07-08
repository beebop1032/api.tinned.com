<?php

namespace App\Controller;

use App\Service\Cache\FrontCacheInvalidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Manual cache flush: invalidates the public catalog cache on the front. Handy after
 * editing data directly through the API (Swagger / scripts / integrations), which does
 * not go through the dashboards that auto-revalidate.
 */
class CacheFlushController extends AbstractController
{
    public function __construct(
        private readonly FrontCacheInvalidator $invalidator,
    ) {}

    #[Route('/api/cache/flush', name: 'api_cache_flush', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function flush(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent() ?: '{}', true);
        $tags = is_array($payload) && isset($payload['tags']) && is_array($payload['tags'])
            ? array_values(array_filter($payload['tags'], 'is_string'))
            : ['catalog'];
        if ($tags === []) {
            $tags = ['catalog'];
        }

        $this->invalidator->flush($tags);

        return new JsonResponse(['flushed' => true, 'tags' => $tags]);
    }
}
