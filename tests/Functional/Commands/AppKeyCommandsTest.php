<?php

declare(strict_types=1);

namespace App\Tests\Functional\Commands;

use App\Entity\AppKey;
use App\Services\BasicAuth;
use Doctrine\ORM\EntityManagerInterface;
use Sabre\HTTP\Request;
use Sabre\HTTP\Response;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class AppKeyCommandsTest extends KernelTestCase
{
    private function runCommand(string $name, array $input = []): CommandTester
    {
        self::bootKernel();
        $tester = new CommandTester((new Application(self::$kernel))->find($name));
        $tester->execute($input);

        return $tester;
    }

    private static function keyByAppId(string $appId): ?AppKey
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        return $em->getRepository(AppKey::class)->findOneByAppId($appId);
    }

    private static function countKeys(): int
    {
        return count(static::getContainer()->get(EntityManagerInterface::class)->getRepository(AppKey::class)->findAll());
    }

    /**
     * @return array{0: string, 1: string} the app id and the secret
     */
    private function credentialsFrom(string $display): array
    {
        $this->assertSame(1, preg_match('/\b(app_[0-9a-f]{24})\b/', $display, $appId));
        $this->assertSame(1, preg_match('/\b([0-9a-f]{64})\b/', $display, $secret));

        return [$appId[1], $secret[1]];
    }

    public function testCreatePrintsTheAppIdAndTheSecretOnce(): void
    {
        $tester = $this->runCommand('davis:app-key:create', ['name' => 'cal.com', '--all-users' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        [$appId, $secret] = $this->credentialsFrom($display);
        $this->assertSame(1, substr_count($display, $secret));
        $this->assertStringContainsString('<login>*'.$appId, $display);

        $key = self::keyByAppId($appId);
        $this->assertNotNull($key);
        $this->assertSame('cal.com', $key->getName());
        $this->assertSame(['*'], $key->getScope());

        $request = new Request('PROPFIND', '/dav/', ['Authorization' => 'Basic '.base64_encode('test_user2*'.$appId.':'.$secret)]);
        [$ok, $principal] = static::getContainer()->get(BasicAuth::class)->check($request, new Response());
        $this->assertTrue($ok);
        $this->assertSame('principals/test_user2', $principal);
    }

    public function testCreateRequiresAScope(): void
    {
        self::bootKernel();
        $before = self::countKeys();

        $tester = $this->runCommand('davis:app-key:create', ['name' => 'nobody']);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertStringContainsString('--all-users', $tester->getDisplay());
        $this->assertSame($before, self::countKeys());
    }

    public function testCreateForAllUsersAndANamedAdmin(): void
    {
        $tester = $this->runCommand('davis:app-key:create', ['name' => 'sync', '--all-users' => true, '--user' => ['test_user']]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        [$appId] = $this->credentialsFrom($tester->getDisplay());
        $this->assertSame(['*', 'test_user'], self::keyByAppId($appId)->getScope());
    }

    public function testCreateWithUsersRestrictsTheScope(): void
    {
        $tester = $this->runCommand('davis:app-key:create', ['name' => 'webmail', '--user' => ['test_user', 'test_user2']]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        [$appId] = $this->credentialsFrom($tester->getDisplay());
        $this->assertSame(['test_user', 'test_user2'], self::keyByAppId($appId)->getScope());
    }

    public function testCreateRejectsUnknownUsers(): void
    {
        self::bootKernel();
        $before = self::countKeys();

        $tester = $this->runCommand('davis:app-key:create', ['name' => 'webmail', '--user' => ['test_user2', 'alice', 'TEST_USER']]);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertStringContainsString('alice, TEST_USER', $tester->getDisplay());
        $this->assertSame($before, self::countKeys());
    }

    public function testCreateRejectsABadExpiry(): void
    {
        self::bootKernel();
        $before = self::countKeys();

        $tester = $this->runCommand('davis:app-key:create', ['name' => 'nope', '--all-users' => true, '--expires-at' => 'yesterday-ish-garbage']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertSame($before, self::countKeys());
    }

    public function testListShowsEveryKey(): void
    {
        $tester = $this->runCommand('davis:app-key:list');

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Test app', $display);
        $this->assertStringContainsString('app_test', $display);
        $this->assertStringContainsString('Revoked app', $display);
        $this->assertStringContainsString('revoked', $display);
        $this->assertStringNotContainsString('$2y$', $display);
    }

    public function testRevoke(): void
    {
        $tester = $this->runCommand('davis:app-key:revoke', ['app-id' => 'app_test']);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertNotNull(self::keyByAppId('app_test')->getRevokedAt());

        $tester = $this->runCommand('davis:app-key:revoke', ['app-id' => 'app_nope']);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());

        $before = self::keyByAppId('app_revoked')->getRevokedAt();
        $tester = $this->runCommand('davis:app-key:revoke', ['app-id' => 'app_revoked']);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertEquals($before, self::keyByAppId('app_revoked')->getRevokedAt());
    }
}
