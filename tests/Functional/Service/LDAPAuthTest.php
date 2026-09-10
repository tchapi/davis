<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Services\LDAPAuth;
use App\Services\Utils;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LDAPAuthTest extends KernelTestCase
{
    protected function setUp(): void
    {
        // ext-ldap is optional for Davis: it is only needed with AUTH_METHOD=LDAP
        if (!function_exists('ldap_escape')) {
            $this->markTestSkipped('The LDAP extension is not loaded');
        }
    }

    private function buildDn(string $pattern, string $username): string
    {
        self::bootKernel();
        $container = static::getContainer();

        $backend = new LDAPAuth(
            $container->get(ManagerRegistry::class),
            $container->get(Utils::class),
            'ldap://127.0.0.1',
            $pattern,
            'mail',
            false,
            'try'
        );

        // buildDn is protected; no setAccessible() needed since PHP 8.1
        return (new \ReflectionMethod($backend, 'buildDn'))->invoke($backend, $username);
    }

    public function testPlaceholdersAreFilledIn(): void
    {
        $this->assertSame(
            'uid=alice,ou=users,dc=example,dc=com',
            $this->buildDn('uid=%u,ou=users,dc=example,dc=com', 'alice')
        );
    }

    public function testUserAndDomainPartsAreSplitOnTheAtSign(): void
    {
        $this->assertSame(
            'uid=alice,dc=example.org',
            $this->buildDn('uid=%U,dc=%d', 'alice@example.org')
        );
    }

    public function testDomainComponentsAreAvailableInReverseOrder(): void
    {
        $this->assertSame(
            'uid=alice,dc=example,dc=org',
            $this->buildDn('uid=%U,dc=%2,dc=%1', 'alice@example.org')
        );
    }

    /**
     * Regression test: the username was interpolated into the DN pattern verbatim, so a name
     * carrying DN syntax added structure to the DN instead of being a value inside it.
     */
    public function testAUsernameCannotInjectDnStructure(): void
    {
        $evil = 'alice,ou=admins';

        $dn = $this->buildDn('uid=%u,ou=users,dc=example,dc=com', $evil);

        $this->assertSame('uid='.ldap_escape($evil, '', LDAP_ESCAPE_DN).',ou=users,dc=example,dc=com', $dn);
        $this->assertStringNotContainsString('uid=alice,ou=admins,', $dn, 'The comma must not stay structural');
    }

    public function testTheDomainPartIsEscapedToo(): void
    {
        $dn = $this->buildDn('uid=%U,dc=%d', 'alice@example.org,ou=admins');

        $this->assertStringNotContainsString('dc=example.org,ou=admins', $dn);
    }
}
