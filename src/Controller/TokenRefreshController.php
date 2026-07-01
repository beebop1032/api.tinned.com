<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Target for the /api/token/refresh route. In practice the gesdinet `refresh_jwt`
 * firewall authenticator intercepts the request and returns the new tokens before
 * this controller runs; it only acts as a fallback so routing always resolves.
 */
class TokenRefreshController
{
    public function __invoke(): Response
    {
        return new JsonResponse(['message' => 'Invalid or missing refresh token.'], Response::HTTP_UNAUTHORIZED);
    }
}
