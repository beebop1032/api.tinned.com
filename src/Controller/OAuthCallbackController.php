<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class OAuthCallbackController extends AbstractController
{
    public function __construct(private readonly string $deepLinkScheme) {}

    #[Route('/oauth/callback', name: 'oauth_callback', methods: ['GET'])]
    public function callback(Request $request): Response
    {
        $code  = (string) $request->query->get('code', '');
        $state = (string) $request->query->get('state', '');

        if ($code === '') {
            throw new NotFoundHttpException();
        }

        $deepLink = sprintf(
            '%s://oauth?code=%s&state=%s',
            $this->deepLinkScheme,
            rawurlencode($code),
            rawurlencode($state)
        );

        return new RedirectResponse($deepLink, 302);
    }
}
