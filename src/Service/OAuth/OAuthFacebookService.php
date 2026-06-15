<?php

namespace App\Service\OAuth;

use App\Entity\User;
use App\Model\User\OAuthLogin;
use Random\RandomException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class OAuthFacebookService extends AbstractOAuthService
{
    public const TYPE = 'facebook';

    /**
     * @throws TransportExceptionInterface
     * @throws RandomException
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function getUser(OAuthLogin $OAuthLogin): User
    {
        $response = $this->httpClient->request('GET', 'https://graph.facebook.com/me', [
            'query' => [
                'fields' => 'id,name,email',
                'access_token' => $OAuthLogin->token,
            ],
        ]);

        $payload = $response->toArray(false);

        $this->checkPayload($OAuthLogin, $payload);

        $email = $payload['email']?? null;
        $oauthId = $payload['id'];

        return $this->processUser($OAuthLogin, $email, $oauthId);
    }
}
