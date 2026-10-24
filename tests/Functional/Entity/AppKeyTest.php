<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Entity\AppKey;
use PHPUnit\Framework\TestCase;

class AppKeyTest extends TestCase
{
    public function testCreateGeneratesAPrefixedHexAppId(): void
    {
        $key = AppKey::create('cal.com', ['*']);

        $this->assertMatchesRegularExpression('/^app_[0-9a-f]{24}$/', $key->getAppId());
        $this->assertNotNull($key->getCreatedAt());
        $this->assertSame(['*'], $key->getScope());
        $this->assertNull($key->getRevokedAt());
    }

    public function testIssueSecretReturnsSixtyFourHexCharsAndStoresOnlyAHash(): void
    {
        $key = AppKey::create('cal.com', ['*']);
        $secret = $key->issueSecret();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $secret);
        $this->assertStringStartsWith('$2y$', $key->getSecretHash());
        $this->assertStringNotContainsString($secret, $key->getSecretHash());
        $this->assertTrue(password_verify($secret, $key->getSecretHash()));
    }

    public function testParseScope(): void
    {
        $this->assertSame(['alice', 'bob', 'carol'], AppKey::parseScope("alice\nbob,carol\n\n"));
        $this->assertSame(['alice', 'bob'], AppKey::parseScope(" alice \r\nbob\nalice, bob"));
        $this->assertSame([], AppKey::parseScope(''), 'An empty scope is not everyone');
        $this->assertSame([], AppKey::parseScope(" ,\n"));
        $this->assertSame(['*'], AppKey::parseScope("*\n*"));
        $this->assertSame(['*', 'alice'], AppKey::parseScope("alice\n*"), 'Names next to * are kept');
    }

    public function testAKeyNeedsAScope(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        AppKey::create('nobody', [' ', '']);
    }

    public function testNames(): void
    {
        $key = AppKey::create('a', ['*', 'admin']);

        $this->assertTrue($key->names('admin'));
        $this->assertFalse($key->names('alice'), 'alice is only covered by *');
        $this->assertFalse($key->names('*'));
        $this->assertSame(['admin'], $key->getNamedUsers());
    }

    public function testForgetUser(): void
    {
        $now = new \DateTimeImmutable();

        $shared = AppKey::create('shared', ['alice', 'bob']);
        $this->assertTrue($shared->forgetUser('alice', $now));
        $this->assertSame(['bob'], $shared->getScope());
        $this->assertTrue($shared->isActive($now), 'bob still uses it');

        $single = AppKey::create('single', ['alice']);
        $this->assertTrue($single->forgetUser('alice', $now));
        $this->assertFalse($single->isActive($now->modify('+1 second')), 'A key only for alice is revoked');
        $this->assertSame(['alice'], $single->getScope(), 'The scope is kept to show who it was for');

        $everyone = AppKey::create('everyone', ['*']);
        $this->assertFalse($everyone->forgetUser('alice', $now));
        $this->assertSame(['*'], $everyone->getScope());
        $this->assertNull($everyone->getRevokedAt());

        $withAdmin = AppKey::create('with admin', ['*', 'admin']);
        $this->assertTrue($withAdmin->forgetUser('admin', $now));
        $this->assertSame(['*'], $withAdmin->getScope());
        $this->assertNull($withAdmin->getRevokedAt());
    }

    public function testAllows(): void
    {
        $everyone = AppKey::create('everyone', ['*']);
        $this->assertTrue($everyone->allows('alice'));
        $this->assertTrue($everyone->allows('anybody@example.org'));

        $some = AppKey::create('some', ['alice', 'bob']);
        $this->assertTrue($some->allows('alice'));
        $this->assertTrue($some->allows('bob'));
        $this->assertFalse($some->allows('carol'));
        $this->assertFalse($some->allows('Alice'), 'Matching is case-sensitive');
        $this->assertFalse($some->allows('*'), 'A literal star login is not the wildcard');
    }

    public function testIsActive(): void
    {
        $now = new \DateTimeImmutable();

        $this->assertTrue(AppKey::create('a', ['*'])->isActive($now));
        $this->assertTrue(AppKey::create('a', ['*'], $now->modify('+1 hour'))->isActive($now));
        $this->assertFalse(AppKey::create('a', ['*'], $now->modify('-1 hour'))->isActive($now));
        $this->assertFalse(AppKey::create('a', ['*'], $now)->isActive($now));
    }

    public function testAcceptsChecksEverything(): void
    {
        $now = new \DateTimeImmutable();
        $key = AppKey::create('a', ['alice']);
        $secret = $key->issueSecret();

        $this->assertTrue($key->accepts('alice', $secret, $now));
        $this->assertFalse($key->accepts('alice', 'wrong', $now));
        $this->assertFalse($key->accepts('bob', $secret, $now));

        $key->revoke($now->modify('-1 second'));
        $this->assertFalse($key->accepts('alice', $secret, $now));
    }

    public function testWasUsedWithin(): void
    {
        $now = new \DateTimeImmutable();
        $key = AppKey::create('a', ['*']);

        $this->assertFalse($key->wasUsedWithin(60, $now));

        $key->setLastUsedAt($now->modify('-30 seconds'));
        $this->assertTrue($key->wasUsedWithin(60, $now));

        $key->setLastUsedAt($now->modify('-120 seconds'));
        $this->assertFalse($key->wasUsedWithin(60, $now));
    }

    public function testRevokeKeepsTheEarlierDate(): void
    {
        $earlier = new \DateTimeImmutable('2020-01-01 00:00:00');
        $later = new \DateTimeImmutable('2030-01-01 00:00:00');

        $key = AppKey::create('a', ['*'])->revoke($earlier)->revoke($later);
        $this->assertEquals($earlier, $key->getRevokedAt());

        $key = AppKey::create('a', ['*'], $later)->revoke($earlier);
        $this->assertEquals($earlier, $key->getRevokedAt(), 'Revoking brings a future expiry forward');
    }
}
