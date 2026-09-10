<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Entity\User;
use App\Services\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UtilsTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private Utils $utils;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->utils = static::getContainer()->get(Utils::class);

        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public static function usernameProvider(): iterable
    {
        yield ['alice', true];
        yield ['first.last@example.org', true];
        yield ['a_b-c.d', true];
        yield ["o'brien@example.org", true];
        yield ['', false];
        yield [null, false];
        yield ['bad/user', false];
        yield ['alice bob', false];
        // plus-addressing is common in mail-derived usernames and is URI-safe in a path segment
        yield ['alice+tag@example.org', true];
        yield ['éric', false];
    }

    /**
     * @dataProvider usernameProvider
     */
    public function testIsValidUsername(?string $username, bool $expected): void
    {
        $this->assertSame($expected, Utils::isValidUsername($username));
    }

    /**
     * Auto-created accounts (IMAP/LDAP) go through this too: provisioning a principal from a
     * username that cannot live in a principal URI would leave the account authenticated but
     * unusable.
     */
    public function testCreatingAUserWithAnUnusableUsernameIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        try {
            $this->utils->createPasswordlessUserWithDefaultObjects('bad/user', 'Bad', 'bad@example.org');
        } finally {
            $this->em->clear();
            $this->assertNull($this->em->getRepository(User::class)->findOneByUsername('bad/user'));
        }
    }

    public function testCreatingAUserWithAValidUsernameWorks(): void
    {
        $this->utils->createPasswordlessUserWithDefaultObjects('new.user@example.org', 'New User', 'new@example.org');
        $this->em->flush();

        $this->assertNotNull($this->em->getRepository(User::class)->findOneByUsername('new.user@example.org'));
    }
}
