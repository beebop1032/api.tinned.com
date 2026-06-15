<?php

namespace App\Service\OAuth;

use App\Entity\User;
use App\Model\User\OAuthLogin;
use Google\Client as GoogleClient;
use Random\RandomException;

class OAuthGoogleService extends AbstractOAuthService
{
    public const TYPE = 'google';

    /**
     * @throws RandomException
     */
    public function getUser(OAuthLogin $OAuthLogin): User
    {
        $client = new GoogleClient(['client_id' => $_ENV['GOOGLE_CLIENT_ID']]);
        $payload = $client->verifyIdToken($OAuthLogin->token);

        $this->checkPayload($OAuthLogin, $payload);

        $email = $payload['email']?? null;
        $oauthId = $payload['sub'];

        return $this->processUser($OAuthLogin, $email, $oauthId);
    }
}
