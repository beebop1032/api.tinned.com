<?php

namespace App\Domain\OAuth;

use App\Entity\User;
use App\Model\User\OAuthLogin;

interface OAuthServiceInterface
{
    public function getUser(OAuthLogin $OAuthLogin): User;
}
