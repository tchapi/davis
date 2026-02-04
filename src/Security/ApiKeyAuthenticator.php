<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApiKeyAuthenticator extends AbstractAuthenticator
{
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        // Disable API endpoint if no API key is set
        if (hash_equals('', trim($apiKey))) {
            throw new \LogicException('API endpoint is disabled.');
        }

        $this->apiKey = $apiKey;
    }

    public function supports(Request $request): ?bool
    {
        // Skip authentication for public health endpoint
        if (preg_match('#^/api/v1/health$#', $request->getPathInfo())) {
            return false;
        }

        // Always attempt to authenticate even if no API token is provided in the request
        // This stops the login page from being shown when accessing API routes
        return true;
    }

    public function authenticate(Request $request): Passport
    {
        $apiToken = $request->headers->get('X-Davis-API-Token');
        if (null === $apiToken) {
            throw new CustomUserMessageAuthenticationException('Missing X-Davis-API-Token header');
        }

        if (false === hash_equals($this->apiKey, $apiToken)) {
            throw new CustomUserMessageAuthenticationException('Invalid X-Davis-API-Token header');
        }

        return new SelfValidatingPassport(new UserBadge('X-DAVIS-API'));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $data = [
            'status' => 'error',
            'message' => $exception->getMessage(),
            'timestamp' => date('c'),
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }
}
