<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * With the bypass on, the dashboard is reached without anyone being logged in.
 */
class AdminAuthBypassTest extends WebTestCase
{
    private ?string $previous = null;

    protected function setUp(): void
    {
        $this->previous = $_ENV['ADMIN_AUTH_BYPASS'] ?? null;
        $_ENV['ADMIN_AUTH_BYPASS'] = $_SERVER['ADMIN_AUTH_BYPASS'] = 'true';
    }

    protected function tearDown(): void
    {
        if (null === $this->previous) {
            unset($_ENV['ADMIN_AUTH_BYPASS'], $_SERVER['ADMIN_AUTH_BYPASS']);
        } else {
            $_ENV['ADMIN_AUTH_BYPASS'] = $_SERVER['ADMIN_AUTH_BYPASS'] = $this->previous;
        }
        parent::tearDown();
    }

    public function testTheMenuKeepsAppKeysAndTheThemeSwitcher(): void
    {
        $client = static::createClient();
        $client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#navUserMenu');
        $this->assertSelectorExists('a[href="/app-keys/"]');
        $this->assertSelectorExists('#bd-theme');
        // Nobody is logged in, so there is nothing to log out of
        $this->assertSelectorNotExists('a[href^="/logout"]');
    }
}
