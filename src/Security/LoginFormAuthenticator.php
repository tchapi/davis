<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    private $doctrine;
    private $urlGenerator;
    private $csrfTokenManager;

    private $adminLogin;
    private $adminPassword;

    public function __construct(ManagerRegistry $doctrine, UrlGeneratorInterface $urlGenerator, CsrfTokenManagerInterface $csrfTokenManager, string $adminLogin, string $adminPassword)
    {
        $this->doctrine = $doctrine;
        $this->urlGenerator = $urlGenerator;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->adminLogin = $adminLogin;
        $this->adminPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate('app_login');
    }

    public function supports(Request $request): bool
    {
        return 'app_login' === $request->attributes->get('_route')
            && $request->isMethod('POST');
    }

    /**
     * The credentials are rejected by the passport rather than by throwing from here, because
     * `login_throttling` only gets to run once a passport exists: throwing earlier means failed
     * attempts are counted but never blocked.
     */
    public function authenticate(Request $request): Passport
    {
        $username = $request->request->getString('_username');
        $password = $request->request->getString('_password');

        $user = $this->doctrine->getRepository(User::class)->findOneByUsername($username);
        if ($user) {
            $username_to_test = $user->getUsername();
            $password_to_test = $user->getPassword();
        } else {
            $username_to_test = $this->adminLogin;
            $password_to_test = $this->adminPassword;
        }

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $username);

        return new Passport(
            new UserBadge($username),
            new CustomCredentials(
                function (string $presentedPassword) use ($username, $username_to_test, $password_to_test): bool {
                    // Both halves are compared, and both in constant time, so the response says
                    // nothing about which one was wrong — an unknown name and a wrong password
                    // fail identically with "Invalid credentials.".
                    $loginMatches = hash_equals($username, $username_to_test);
                    $passwordMatches = password_verify($presentedPassword, $password_to_test);

                    return $loginMatches && $passwordMatches;
                },
                $password
            ),
            [new CsrfTokenBadge('authenticate', $request->request->getString('_csrf_token'))]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey): ?Response
    {
        if (in_array('ROLE_ADMIN', $token->getRoleNames(), true)) {
            return new RedirectResponse($this->urlGenerator->generate('dashboard'));
        } elseif (in_array('ROLE_USER', $token->getRoleNames(), true)) {
            return new RedirectResponse($this->urlGenerator->generate('user_user', ['userId' => $token->getUser()->getUserId()]));
        }

        // XXX: this should not be reachable
        return new RedirectResponse($this->urlGenerator->generate('/'));
    }
}
