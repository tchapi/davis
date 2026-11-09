<?php

declare(strict_types=1);

namespace App\Tests\Functional\Commands;

use App\Command\AppKeyPruneCommand;
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
        $tester = new CommandTester((new Application(self::bootKernel()))->find($name));
        $tester->execute($input);

        return $tester;
    }

    private static function findKeyByAppId(string $appId): ?AppKey
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        return $em->getRepository(AppKey::class)->findOneByAppId($appId);
    }

    private static function keyByAppId(string $appId): AppKey
    {
        $key = self::findKeyByAppId($appId);
        self::assertNotNull($key, "No app key with app id $appId");

        return $key;
    }

    private static function countKeys(): int
    {
        return count(static::getContainer()->get(EntityManagerInterface::class)->getRepository(AppKey::class)->findAll());
    }

    /**
     * Saves a key, with one usage row so that the tests can check the history goes away with it.
     */
    private static function persistKey(string $name, ?\DateTimeImmutable $revokedAt): AppKey
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $key = AppKey::create($name, [AppKey::ALL_USERS], $revokedAt);
        $key->issueSecret();
        $em->persist($key);
        $em->flush();

        $connection = $em->getConnection();
        $format = $connection->getDatabasePlatform()->getDateTimeFormatString();
        $connection->insert('app_key_usages', [
            'app_key_id' => $key->getId(),
            'login' => 'test_user',
            'first_used_at' => (new \DateTimeImmutable('-2 days'))->format($format),
            'last_used_at' => (new \DateTimeImmutable('-1 hour'))->format($format),
        ]);

        return $key;
    }

    private static function countUsages(AppKey $key): int
    {
        return (int) static::getContainer()->get(EntityManagerInterface::class)->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM app_key_usages WHERE app_key_id = ?',
            [$key->getId()]
        );
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

    public function testPruneDeletesTheKeysRevokedLongEnoughAgo(): void
    {
        self::bootKernel();
        $old = self::persistKey('old', new \DateTimeImmutable('-10 days'));
        $recent = self::persistKey('recent', new \DateTimeImmutable('-3 days'));
        $active = self::persistKey('active', null);
        $expiring = self::persistKey('expiring', new \DateTimeImmutable('+10 days'));

        $tester = $this->runCommand('davis:app-key:prune', ['days' => '7']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString($old->getAppId().' old revoked', $tester->getDisplay());
        $this->assertNull(self::findKeyByAppId($old->getAppId()));
        $this->assertSame(0, self::countUsages($old));
        $this->assertNotNull(self::keyByAppId($recent->getAppId()));
        $this->assertSame(1, self::countUsages($recent));
        $this->assertNotNull(self::keyByAppId($active->getAppId()));
        $this->assertNotNull(self::keyByAppId($expiring->getAppId()));
    }

    public function testPruneWithZeroDaysDeletesEveryRevokedKeyButNotAnExpiry(): void
    {
        self::bootKernel();
        $justRevoked = self::persistKey('just revoked', new \DateTimeImmutable('-1 minute'));
        $active = self::persistKey('active', null);
        $expiring = self::persistKey('expiring', new \DateTimeImmutable('+1 hour'));

        $tester = $this->runCommand('davis:app-key:prune', ['days' => '0']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertNull(self::findKeyByAppId($justRevoked->getAppId()));
        // app_revoked comes from the fixtures, revoked a day ago
        $this->assertNull(self::findKeyByAppId('app_revoked'));
        $this->assertNotNull(self::keyByAppId($active->getAppId()));
        $this->assertNotNull(self::keyByAppId($expiring->getAppId()));
        $this->assertNotNull(self::keyByAppId('app_test'));
    }

    public function testPruneDryRunDeletesNothing(): void
    {
        self::bootKernel();
        $old = self::persistKey('old', new \DateTimeImmutable('-10 days'));
        $before = self::countKeys();

        $tester = $this->runCommand('davis:app-key:prune', ['days' => '7', '--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Would delete '.$old->getAppId(), $tester->getDisplay());
        $this->assertSame($before, self::countKeys());
        $this->assertSame(1, self::countUsages($old));
    }

    public function testPruneWithNothingToDeleteSucceeds(): void
    {
        self::bootKernel();
        $before = self::countKeys();

        // the fixtures have one revoked key, a day old
        $tester = $this->runCommand('davis:app-key:prune', ['days' => '365']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('nothing to delete', $tester->getDisplay());
        $this->assertSame($before, self::countKeys());
    }

    public function testPruneRejectsABadNumberOfDays(): void
    {
        self::bootKernel();
        $before = self::countKeys();

        // The last two used to pass ctype_digit() and then blow up in DateInterval (or saturate to PHP_INT_MAX)
        foreach (['-1', 'abc', '1.5', '', '1e3', (string) (AppKeyPruneCommand::MAX_DAYS + 1), '99999999999999999999'] as $days) {
            $tester = $this->runCommand('davis:app-key:prune', ['days' => $days]);
            $this->assertSame(Command::INVALID, $tester->getStatusCode(), $days);
            $this->assertStringContainsString('not a number of days', $tester->getDisplay(), $days);
        }

        $this->assertSame($before, self::countKeys());
    }

    public function testDeleteRefusesAnActiveKey(): void
    {
        self::bootKernel();
        $active = self::persistKey('active', null);
        $expiring = self::persistKey('expiring', new \DateTimeImmutable('+10 days'));

        foreach ([$active, $expiring] as $key) {
            $tester = $this->runCommand('davis:app-key:delete', ['app-id' => $key->getAppId()]);

            $this->assertSame(Command::FAILURE, $tester->getStatusCode());
            $this->assertStringContainsString('davis:app-key:revoke', $tester->getDisplay());
            $this->assertNotNull(self::keyByAppId($key->getAppId()));
            $this->assertSame(1, self::countUsages($key));
        }
    }

    public function testDeleteRemovesARevokedKeyAndItsUsages(): void
    {
        self::bootKernel();
        $revoked = self::persistKey('revoked', new \DateTimeImmutable('-1 minute'));

        $tester = $this->runCommand('davis:app-key:delete', ['app-id' => $revoked->getAppId()]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertNull(self::findKeyByAppId($revoked->getAppId()));
        $this->assertSame(0, self::countUsages($revoked));
    }

    public function testDeleteFailsOnAnUnknownKey(): void
    {
        self::bootKernel();
        $before = self::countKeys();

        $tester = $this->runCommand('davis:app-key:delete', ['app-id' => 'app_nope']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertSame($before, self::countKeys());
    }
}
