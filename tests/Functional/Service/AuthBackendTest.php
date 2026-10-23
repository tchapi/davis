<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Entity\AppKey;
use App\Repository\AppKeyRepository;
use App\Services\AbstractAuth;
use App\Services\BasicAuth;
use App\Services\IMAPAuth;
use App\Services\LDAPAuth;
use Doctrine\ORM\EntityManagerInterface;
use Sabre\HTTP\Request;
use Sabre\HTTP\Response;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AuthBackendTest extends KernelTestCase
{
    /**
     * Runs the backend through sabre's public entry point (`check()`), exactly as the
     * DAV server does, for a given `user:password` pair.
     */
    private static function check(AbstractAuth $backend, string $userPass): array
    {
        $request = new Request('PROPFIND', '/dav/', ['Authorization' => 'Basic '.base64_encode($userPass)]);

        return $backend->check($request, new Response());
    }

    /**
     * A backend that accepts everything, to prove the guard in AbstractAuth never lets
     * an empty username or password reach the concrete backend.
     */
    private static function acceptAllBackend(): AbstractAuth
    {
        return new class extends AbstractAuth {
            public array $seen = [];

            protected function checkCredentials(string $username, string $password): bool
            {
                $this->seen[] = [$username, $password];

                return true;
            }
        };
    }

    /**
     * @param string[] $scope
     */
    private function appKeyFor(array $scope = ['*'], ?\DateTimeImmutable $revokedAt = null, string $secret = 'app-secret'): AppKey
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $key = AppKey::create('Test', $scope, $revokedAt)
            ->setSecretHash(password_hash($secret, PASSWORD_DEFAULT));
        $em->persist($key);
        $em->flush();

        return $key;
    }

    private function guardedBackend(): AbstractAuth
    {
        $backend = self::acceptAllBackend();
        $backend->setAppKeyRepository(static::getContainer()->get(AppKeyRepository::class));

        return $backend;
    }

    public function testEmptyPasswordNeverReachesTheBackend(): void
    {
        $backend = self::acceptAllBackend();

        [$ok] = self::check($backend, 'test_user:');

        $this->assertFalse($ok);
        $this->assertSame([], $backend->seen);
    }

    public function testEmptyUsernameNeverReachesTheBackend(): void
    {
        $backend = self::acceptAllBackend();

        [$ok] = self::check($backend, ':password');

        $this->assertFalse($ok);
        $this->assertSame([], $backend->seen);
    }

    public function testNonEmptyCredentialsAreDelegatedToTheBackend(): void
    {
        $backend = self::acceptAllBackend();

        [$ok, $principal] = self::check($backend, 'test_user:pa:ss');

        $this->assertTrue($ok);
        $this->assertSame('principals/test_user', $principal);
        $this->assertSame([['test_user', 'pa:ss']], $backend->seen);
    }

    public function testAllBackendsShareTheGuard(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        foreach ([BasicAuth::class, IMAPAuth::class, LDAPAuth::class] as $class) {
            $backend = $container->get($class);
            $this->assertInstanceOf(AbstractAuth::class, $backend, $class.' must extend AbstractAuth');

            [$ok] = self::check($backend, 'test_user:');
            $this->assertFalse($ok, $class.' must reject an empty password');

            [$ok] = self::check($backend, ':password');
            $this->assertFalse($ok, $class.' must reject an empty username');

            // The fixtures hold `app_test` (secret `app-secret`, every user): IMAP and LDAP have no
            // server here, so this only passes if the app key path never reaches them
            [$ok, $principal] = self::check($backend, 'alice*app_test:app-secret');
            $this->assertTrue($ok, $class.' must accept an app key');
            $this->assertSame('principals/alice', $principal, $class.' must impersonate the login part');

            [$ok] = self::check($backend, 'alice*app_test:wrong');
            $this->assertFalse($ok, $class.' must reject a wrong app key secret');
        }
    }

    public function testWithoutARepositoryAStarIsJustACharacter(): void
    {
        $backend = self::acceptAllBackend();

        [$ok, $principal] = self::check($backend, 'alice*app_test:secret');

        $this->assertTrue($ok);
        $this->assertSame('principals/alice*app_test', $principal);
        $this->assertSame([['alice*app_test', 'secret']], $backend->seen);
    }

    public function testAnAppKeyImpersonatesTheLoginWithoutAskingTheBackend(): void
    {
        $key = $this->appKeyFor();
        $backend = $this->guardedBackend();

        [$ok, $principal] = self::check($backend, 'alice*'.$key->getAppId().':app-secret');

        $this->assertTrue($ok);
        $this->assertSame('principals/alice', $principal);
        $this->assertSame([], $backend->seen);
    }

    /**
     * A wrong secret must not fall through to the backend with `alice*app_...` as a username:
     * it would be a directory lookup with an attacker-chosen password.
     */
    public function testAWrongSecretIsRejectedWithoutAskingTheBackend(): void
    {
        $key = $this->appKeyFor();
        $backend = $this->guardedBackend();

        [$ok] = self::check($backend, 'alice*'.$key->getAppId().':wrong');

        $this->assertFalse($ok);
        $this->assertSame([], $backend->seen);
    }

    public function testARevokedKeyIsRejected(): void
    {
        $key = $this->appKeyFor(['*'], new \DateTimeImmutable('-1 minute'));
        $backend = $this->guardedBackend();

        [$ok] = self::check($backend, 'alice*'.$key->getAppId().':app-secret');

        $this->assertFalse($ok);
        $this->assertSame([], $backend->seen);
    }

    public function testAKeyExpiringInTheFutureStillWorks(): void
    {
        $key = $this->appKeyFor(['*'], new \DateTimeImmutable('+1 day'));
        $backend = $this->guardedBackend();

        [$ok, $principal] = self::check($backend, 'alice*'.$key->getAppId().':app-secret');

        $this->assertTrue($ok);
        $this->assertSame('principals/alice', $principal);
    }

    public function testALoginOutOfScopeIsRejected(): void
    {
        $key = $this->appKeyFor(['bob']);
        $backend = $this->guardedBackend();

        [$ok] = self::check($backend, 'alice*'.$key->getAppId().':app-secret');
        $this->assertFalse($ok);
        $this->assertSame([], $backend->seen);

        [$ok, $principal] = self::check($backend, 'bob*'.$key->getAppId().':app-secret');
        $this->assertTrue($ok);
        $this->assertSame('principals/bob', $principal);
    }

    public function testAStarScopeAllowsEveryLogin(): void
    {
        $key = $this->appKeyFor(['*']);
        $backend = $this->guardedBackend();

        foreach (['alice', 'bob', 'first.last+tag@example.org'] as $login) {
            [$ok, $principal] = self::check($backend, $login.'*'.$key->getAppId().':app-secret');

            $this->assertTrue($ok, $login.' must be allowed');
            $this->assertSame('principals/'.$login, $principal);
        }
    }

    public function testTheAppKeyNeedsALogin(): void
    {
        $key = $this->appKeyFor();

        foreach (['*'.$key->getAppId(), 'a/b*'.$key->getAppId()] as $username) {
            $backend = $this->guardedBackend();

            [$ok] = self::check($backend, $username.':app-secret');

            $this->assertFalse($ok, sprintf('%s must not authenticate', var_export($username, true)));
            $this->assertSame([], $backend->seen, 'The backend must not even be consulted');
        }
    }

    public function testTheLoginIsEverythingBeforeTheLastStar(): void
    {
        $key = $this->appKeyFor();
        $backend = $this->guardedBackend();

        [$ok, $principal] = self::check($backend, 'a*b*'.$key->getAppId().':app-secret');

        $this->assertTrue($ok);
        $this->assertSame('principals/a*b', $principal);
    }

    public function testAnUnknownSuffixIsDelegatedToTheBackendAsIs(): void
    {
        $backend = $this->guardedBackend();

        [$ok, $principal] = self::check($backend, 'alice*other:secret');

        $this->assertTrue($ok);
        $this->assertSame('principals/alice*other', $principal);
        $this->assertSame([['alice*other', 'secret']], $backend->seen);
    }

    public function testLastUsedAtIsStampedAtMostOnceAMinute(): void
    {
        $key = $this->appKeyFor();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $backend = $this->guardedBackend();
        $userPass = 'alice*'.$key->getAppId().':app-secret';

        $this->assertNull($key->getLastUsedAt());
        self::check($backend, $userPass);
        $em->refresh($key);
        $this->assertNotNull($key->getLastUsedAt(), 'A first use must be stamped');

        $recent = new \DateTimeImmutable('-30 seconds');
        $key->setLastUsedAt($recent);
        $em->flush();
        self::check($backend, $userPass);
        $em->refresh($key);
        $this->assertSame($recent->format('Y-m-d H:i:s'), $key->getLastUsedAt()->format('Y-m-d H:i:s'), 'A use within the minute must not write');

        $old = new \DateTimeImmutable('-120 seconds');
        $key->setLastUsedAt($old);
        $em->flush();
        self::check($backend, $userPass);
        $em->refresh($key);
        $this->assertGreaterThan($old, $key->getLastUsedAt(), 'A use after the minute must be stamped again');
    }

    public function testBasicAuthStillAcceptsValidCredentials(): void
    {
        self::bootKernel();
        $backend = static::getContainer()->get(BasicAuth::class);

        [$ok, $principal] = self::check($backend, 'test_user:password');
        $this->assertTrue($ok);
        $this->assertSame('principals/test_user', $principal);

        [$ok] = self::check($backend, 'test_user:wrong');
        $this->assertFalse($ok);
    }

    /**
     * A username becomes the principal URI (`principals/<username>`), so one containing a
     * slash would address a different node — `alice/calendar-proxy-write` is exactly the URI
     * Davis uses for alice's delegation proxy.
     */
    public function testUsernamesThatWouldBreakThePrincipalUriAreRejected(): void
    {
        foreach (['alice/calendar-proxy-write', 'alice\\bob', 'alice bob', "alice\tbob", "alice\nbob"] as $username) {
            $backend = self::acceptAllBackend();

            [$ok] = self::check($backend, $username.':password');

            $this->assertFalse($ok, sprintf('%s must not authenticate', var_export($username, true)));
            $this->assertSame([], $backend->seen, 'The backend must not even be consulted');
        }
    }

    public function testAnUnusualButStructurallySoundUsernameStillAuthenticates(): void
    {
        $backend = self::acceptAllBackend();

        [$ok, $principal] = self::check($backend, 'first.last+tag@example.org:password');

        $this->assertTrue($ok);
        $this->assertSame('principals/first.last+tag@example.org', $principal);
    }

    /**
     * A backend may report the username as the directory spells it; the principal must then be
     * built from that, otherwise a case-variant login lands on a principal that does not exist.
     */
    public function testACanonicalUsernameBecomesThePrincipal(): void
    {
        $backend = new class extends AbstractAuth {
            protected function checkCredentials(string $username, string $password): bool
            {
                $this->setCanonicalUsername(strtolower($username));

                return true;
            }
        };

        [$ok, $principal] = self::check($backend, 'ALICE:password');

        $this->assertTrue($ok);
        $this->assertSame('principals/alice', $principal);
    }

    public function testACanonicalUsernameThatWouldBreakThePrincipalUriIsIgnored(): void
    {
        $backend = new class extends AbstractAuth {
            protected function checkCredentials(string $username, string $password): bool
            {
                $this->setCanonicalUsername('some/other/path');

                return true;
            }
        };

        [$ok, $principal] = self::check($backend, 'alice:password');

        $this->assertTrue($ok);
        $this->assertSame('principals/alice', $principal, 'It must fall back to the name the client sent');
    }

    public function testTheCanonicalUsernameDoesNotLeakBetweenAttempts(): void
    {
        $backend = new class extends AbstractAuth {
            public bool $canonicalise = true;

            protected function checkCredentials(string $username, string $password): bool
            {
                if ($this->canonicalise) {
                    $this->setCanonicalUsername('canonical');
                }

                return true;
            }
        };

        [, $first] = self::check($backend, 'ALICE:password');
        $this->assertSame('principals/canonical', $first);

        $backend->canonicalise = false;
        [, $second] = self::check($backend, 'BOB:password');
        $this->assertSame('principals/BOB', $second, 'A later attempt must not reuse the previous canonical name');
    }
}
