<?php

namespace App\Service\OAuth;

use ApiPlatform\Validator\Exception\ValidationException;
use App\Domain\OAuth\OAuthServiceInterface;
use App\Entity\User;
use App\Model\User\OAuthLogin;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AbstractOAuthService implements OAuthServiceInterface
{
    public function __construct(
        protected UserRepository $userRepository,
        protected EntityManagerInterface $em,
        protected HttpClientInterface $httpClient
    ){}

    public function getUser(OAuthLogin $OAuthLogin): User
    {
        throw new \RuntimeException('Not implemented yet');
    }

    protected function checkPayload(OAuthLogin $OAuthLogin, false|array $payload) : void
    {
        if (!$payload) {
            $violations = new ConstraintViolationList([
                new ConstraintViolation(
                    message: 'Invalid '.$OAuthLogin->provider.' token.',
                    messageTemplate: null,
                    parameters: [],
                    root: null,
                    propertyPath: 'token',
                    invalidValue: $OAuthLogin->token
                )
            ]);

            throw new ValidationException($violations);
        }
    }

    protected function checkIdentifier(?string $email, ?string $oAuthId) : void
    {
        if ($oAuthId === null) {
            $violations = new ConstraintViolationList([
                new ConstraintViolation(
                    message: 'Invalid token: identifier missing in payload.',
                    messageTemplate: null,
                    parameters: [],
                    root: null,
                    propertyPath: 'token',
                    invalidValue: 'identifier'
                )
            ]);

            throw new ValidationException($violations);
        }
    }

    /**
     * @throws RandomException
     */
    protected function createUser(OAuthLogin $OAuthLogin, string $oAuthId, ?string $email): User
    {
        $user = new User();
        $user->setEmail($email ?? $OAuthLogin->provider.'_'.$oAuthId.'@no-email.local');
        $user->setPassword(bin2hex(random_bytes(16)));
        $user->setRoles(['ROLE_USER']);
        $user->setOauthProvider($OAuthLogin->provider);
        $user->setOauthId($oAuthId);

        return $user;
    }

    /**
     * @throws RandomException
     */
    protected function processUser(OAuthLogin $OAuthLogin, ?string $email, int|string $oauthId): User
    {
        $this->checkIdentifier($email, $oauthId);

        $user = null;

        if($email !== null) {
            $user = $this->userRepository->findOneByEmail($email);
        }

        if(!$user) {
            $user = $this->userRepository->findOneOAuthProviderAndOAuthId($OAuthLogin->provider, $oauthId);

            if($user && $email !== null) {
                $user->setEmail($email);
            }
        }

        if(!$user) {
            $user = $this->createUser($OAuthLogin, $oauthId, $email);
        }

        $user->setOAuthProvider($OAuthLogin->provider);
        $user->setOAuthId($oauthId);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
