<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

class OidcController extends AbstractController
{
    #[Route('/dashboard/connect/oidc', name: 'app_oidc_connect')]
    public function connect(ClientRegistry $registry): RedirectResponse
    {
        return $registry->getClient('oidc')->redirect([], []);
    }

    // The actual token exchange is done by OidcAuthenticator.
    // This action is never reached; it just needs to exist for routing.
    #[Route('/dashboard/connect/oidc/check', name: 'app_oidc_check')]
    public function check(): void {}
}
