<?php

namespace App\Factory\OAuth;

use App\Domain\OAuth\OAuthServiceInterface;
use App\Exception\CannotImportException;
use App\Service\OAuth\OAuthAppleService;
use App\Service\OAuth\OAuthFacebookService;
use App\Service\OAuth\OAuthGoogleService;

readonly class OAuthFactory
{
    public function __construct(
        private OAuthGoogleService $OAuthGoogleService,
        private OAuthAppleService $OAuthAppleService,
        private OAuthFacebookService $OAuthFacebookService,
    ) {
    }

    /**
     * @throws CannotImportException
     */
    public function get(string $type): OAuthServiceInterface
    {
        if ($type === OAuthGoogleService::TYPE) {
            return $this->OAuthGoogleService;
        }

        if ($type === OAuthAppleService::TYPE) {
            return $this->OAuthAppleService;
        }

        if ($type === OAuthFacebookService::TYPE) {
            return $this->OAuthFacebookService;
        }

        throw new CannotImportException('OAuth type not supported: ' . $type);
    }
}
