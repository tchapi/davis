<?php

namespace App\Security;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class OidcAuthenticator extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly RouterInterface $router,
    ) {}

    public function supports(Request $request): ?bool
    {
        // Never activate if auth is bypassed or not in oidc mode
        if ($this->adminAuthMethod !== 'oidc') {
            return false;
        }

        return $request->attributes->get('_route') === 'app_oidc_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('oidc');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($client, $accessToken) {
                // Fetch claims from userinfo endpoint
                $resourceOwner = $client->fetchUserFromToken($accessToken);
                $claims = $resourceOwner->toArray();

                // Prefer email, fall back to sub
                $username = $claims['email'] ?? $claims['sub'] ?? $resourceOwner->getId();

                return new \Symfony\Component\Security\Core\User\InMemoryUser(
                    $username,
                    null,
                    ['ROLE_ADMIN'],
                );
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->router->generate('dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new RedirectResponse($this->router->generate('login', [
            'error' => $exception->getMessageKey(),
        ]));
    }

    // AuthenticationEntryPointInterface: redirect to OIDC when unauthenticated
    public function start(Request $request, ?AuthenticationEntryPointInterface $authException = null): Response
    {
        if ($this->adminAuthMethod === 'oidc') {
            return new RedirectResponse($this->router->generate('app_oidc_connect'));
        }
        // Fall back to the local login page
        return new RedirectResponse($this->router->generate('login'));
    }
}
