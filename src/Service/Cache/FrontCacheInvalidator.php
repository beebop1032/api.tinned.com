<?php

namespace App\Service\Cache;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Busts the Next.js data cache by calling the front's on-demand revalidation webhook
 * (POST {APP_FRONT_URL}/api/revalidate). Lets a change made directly through the API
 * (Swagger, scripts, integrations) refresh the public site without waiting for the TTL.
 * Best-effort: a failure here never breaks the caller.
 */
final class FrontCacheInvalidator
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(APP_FRONT_URL)%')]
        private readonly string $frontUrl = 'http://localhost:4001',
        #[Autowire('%env(default::REVALIDATE_SECRET)%')]
        private readonly ?string $secret = null,
    ) {
    }

    /**
     * @param list<string> $tags Cache tags to invalidate (default: the whole catalog).
     */
    public function flush(array $tags = ['catalog']): void
    {
        $headers = ['Content-Type' => 'application/json'];
        if ($this->secret) {
            $headers['x-revalidate-secret'] = $this->secret;
        }

        try {
            $this->httpClient->request('POST', rtrim($this->frontUrl, '/') . '/api/revalidate', [
                'headers' => $headers,
                'json' => ['tags' => $tags],
                'timeout' => 3,
            ])->getStatusCode();
        } catch (\Throwable $e) {
            $this->logger->warning('Front cache revalidation failed: {msg}', ['msg' => $e->getMessage()]);
        }
    }
}
