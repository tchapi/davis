<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Services\AbstractAuth;
use App\Services\BasicAuth;
use App\Services\IMAPAuth;
use App\Services\LDAPAuth;
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
        }
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
}
