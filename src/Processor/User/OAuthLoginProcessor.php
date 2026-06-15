<?php

namespace App\Processor\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\User;
use App\Exception\CannotImportException;
use App\Factory\OAuth\OAuthFactory;
use App\Model\User\OAuthLogin;
use App\Model\User\RegisterUserResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Random\RandomException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

readonly class OAuthLoginProcessor implements ProcessorInterface
{
    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
        private OAuthFactory $oAuthFactory,
    ) {}

    /**
     * @throws CannotImportException
     * @throws RandomException
     * @throws \JsonException
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RegisterUserResponse
    {
        if (!$data instanceof OAuthLogin) {
            throw new BadRequestException('Invalid payload');
        }

        try {
            $oAuthService = $this->oAuthFactory->get($data->provider);
        } catch (CannotImportException $e) {
            $violations = new ConstraintViolationList([
                new ConstraintViolation(
                    message: $e->getMessage(),
                    messageTemplate: null,
                    parameters: [],
                    root: null,
                    propertyPath: "provider",
                    invalidValue: $data->provider
                )
            ]);

            throw new ValidationException($violations);
        }

        $user = $oAuthService->getUser($data);
        $token = $this->jwtManager->create($user);

        return new RegisterUserResponse(
            $user->getId(),
            $user->getEmail(),
            $token,
            (string) $user->getFirstName(),
            (string) $user->getLastName(),
            (string) $user->getPhone()
        );
    }
}
