<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Security\AdminUser;
use App\Services\BasicAuth;
use Sabre\HTTP\Request;
use Sabre\HTTP\Response;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Exception\CommandNotFoundException;

/**
 * .env.test turns app keys on. These tests turn them off before the kernel boots.
 */
class AppKeysDisabledTest extends WebTestCase
{
    private ?string $previous = null;

    protected function setUp(): void
    {
        $this->previous = $_ENV['APP_KEYS_ENABLED'] ?? null;
        $_ENV['APP_KEYS_ENABLED'] = $_SERVER['APP_KEYS_ENABLED'] = 'false';
    }

    protected function tearDown(): void
    {
        $_ENV['APP_KEYS_ENABLED'] = $_SERVER['APP_KEYS_ENABLED'] = $this->previous;
        parent::tearDown();
    }

    public function testThePagesAreNotFound(): void
    {
        $client = static::createClient();
        $client->loginUser(new AdminUser('admin', 'test'));

        foreach (['/app-keys/', '/app-keys/new'] as $url) {
            $client->request('GET', $url);
            $this->assertResponseStatusCodeSame(404, $url);
        }
    }

    public function testTheMenuHasNoLink(): void
    {
        $client = static::createClient();
        $client->loginUser(new AdminUser('admin', 'test'));
        $client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('a[href="/app-keys/"]');
    }

    public function testTheCommandsAreNotAvailable(): void
    {
        self::bootKernel();
        $application = new Application(self::$kernel);

        foreach (['davis:app-key:create', 'davis:app-key:list', 'davis:app-key:revoke'] as $name) {
            try {
                $application->find($name);
                $this->fail($name.' must not be available');
            } catch (CommandNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testAKeyDoesNotLogIn(): void
    {
        self::bootKernel();
        $request = new Request('PROPFIND', '/dav/', ['Authorization' => 'Basic '.base64_encode('test_user2*app_test:app-secret')]);

        // The fixtures have an active `app_test` key. With app keys off, the whole username goes
        // to the backend, and there is no user with that name.
        [$ok] = static::getContainer()->get(BasicAuth::class)->check($request, new Response());

        $this->assertFalse($ok);
    }
}
