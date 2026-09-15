<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Services\IMAPAuth;
use App\Services\Utils;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class IMAPAuthTest extends KernelTestCase
{
    private function backendFor(string $url, string $encryptionMethod = 'ssl'): IMAPAuth
    {
        self::bootKernel();
        $container = static::getContainer();

        return new IMAPAuth(
            $container->get(ManagerRegistry::class),
            $container->get(Utils::class),
            $url,
            false,
            $encryptionMethod,
            true
        );
    }

    /**
     * @return array{0: ?string, 1: ?int}
     */
    private function hostAndPortOf(IMAPAuth $backend): array
    {
        $reflection = new \ReflectionObject($backend);

        return [
            $reflection->getProperty('IMAPHost')->getValue($backend),
            $reflection->getProperty('IMAPPort')->getValue($backend),
        ];
    }

    /**
     * @dataProvider authUrls
     */
    public function testTheHostAndPortAreReadFromTheAuthUrl(string $url, ?string $host, int $port): void
    {
        [$parsedHost, $parsedPort] = $this->hostAndPortOf($this->backendFor($url));

        $this->assertSame($host, $parsedHost, $url.' should yield that host');
        $this->assertSame($port, $parsedPort, $url.' should yield that port');
    }

    public static function authUrls(): iterable
    {
        yield 'the documented host:port form' => ['imap.example.com:993', 'imap.example.com', 993];
        yield 'a bare host' => ['imap.example.com', 'imap.example.com', 993];
        yield 'a scheme is ignored, the port is kept' => ['imaps://imap.example.com:993', 'imap.example.com', 993];
        yield 'a scheme without a port' => ['ssl://imap.example.com', 'imap.example.com', 993];
        yield 'an IPv6 literal' => ['[::1]:993', '[::1]', 993];
        yield 'a trailing path is not part of the host' => ['imap.example.com//foo', 'imap.example.com', 993];
        yield 'a leading double slash' => ['//imap.example.com:993', 'imap.example.com', 993];
        yield 'a leading double slash in front of a scheme' => ['//imaps://imap.example.com', 'imap.example.com', 993];
        yield 'the null placeholder counts as unset, not as a hostname' => ['null', null, 993];
        yield 'an empty value leaves no host' => ['', null, 993];
    }

    /**
     * The port defaults to the one matching the encryption method whenever the url omits it.
     */
    public function testTheDefaultPortFollowsTheEncryptionMethod(): void
    {
        $this->assertSame(143, $this->hostAndPortOf($this->backendFor('imap.example.com', 'false'))[1]);
        $this->assertSame(993, $this->hostAndPortOf($this->backendFor('imap.example.com', 'tls'))[1]);
        $this->assertSame(993, $this->hostAndPortOf($this->backendFor('imap.example.com', 'ssl'))[1]);

        // An explicit port always wins over the default
        $this->assertSame(1143, $this->hostAndPortOf($this->backendFor('imap.example.com:1143', 'false'))[1]);
    }

    public function testAnUnparsableAuthUrlIsReportedClearly(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('IMAP_AUTH_URL could not be parsed');

        $this->backendFor('///');
    }

    /**
     * The backend is a constructor argument of the DAV controller, so it is built on every request
     * even when AUTH_METHOD is not IMAP. A missing host therefore cannot fail at boot, and has to
     * deny the login instead of opening a connection to nowhere.
     */
    public function testALoginIsDeniedWhenTheAuthUrlCarriesNoHost(): void
    {
        $backend = $this->backendFor('');

        $log = tempnam(sys_get_temp_dir(), 'imap-auth-test');
        $previous = ini_set('error_log', $log);

        try {
            $denied = (new \ReflectionMethod($backend, 'imapOpen'))->invoke($backend, 'someone', 'password');
            $logged = file_get_contents($log);
        } finally {
            ini_set('error_log', $previous);
            unlink($log);
        }

        $this->assertFalse($denied);
        $this->assertStringContainsString('IMAP_AUTH_URL has no host', $logged, 'The reason has to reach the log');
    }
}
