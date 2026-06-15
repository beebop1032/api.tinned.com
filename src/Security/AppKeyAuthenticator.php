<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class AppKeyAuthenticator extends AbstractAuthenticator
{
    private const API_KEY_HEADER = 'X-APP-API-KEY';

    public function __construct(
        private readonly string $applicationApiKey,
    ) {}

    public function supports(Request $request): ?bool
    {
        $auth = (string) $request->headers->get('Authorization', '');
        if (str_starts_with($auth, 'Bearer ')) {
            return false;
        }

        return $request->headers->has(self::API_KEY_HEADER);
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $provided = (string) $request->headers->get(self::API_KEY_HEADER, '');

        if (!hash_equals($this->applicationApiKey, $provided)) {
            throw new AuthenticationException('Invalid app api key.');
        }

        // "app_client" doit exister dans le provider app_key_users
        return new SelfValidatingPassport(new UserBadge('app_client'));
    }

    public function onAuthenticationSuccess(Request $request, $token, string $firewallName): ?JsonResponse
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?JsonResponse
    {
        return new JsonResponse(['message' => 'Unauthorized2'], 401);
    }
}
