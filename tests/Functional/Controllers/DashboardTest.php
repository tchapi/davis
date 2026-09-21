<?php

namespace App\Tests\Functional;

use App\Security\AdminUser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DashboardTest extends WebTestCase
{
    public function testIndexPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h3', 'Davis');

        $this->assertSelectorExists('li.caldav');
        $this->assertSelectorExists('li.carddav');
        $this->assertSelectorExists('li.webdav');
    }

    public function testDashboardPageUnlogged(): void
    {
        $client = static::createClient();
        $client->request('GET', '/dashboard');

        $this->assertResponseRedirects('/login');
    }

    public function testLoginPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Please sign in');
        $this->assertSelectorExists('nav.navbar');
    }

    public function testLoginIncorrectUsername(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $form = $crawler->selectButton('Submit')->form();
        $form['_username']->setValue('bad_'.$_ENV['ADMIN_LOGIN']);
        $form['_password']->setValue('bad_password');

        $client->submit($form);
        $this->assertResponseRedirects('/login');
        $crawler = $client->followRedirect();
        $this->assertResponseIsSuccessful();

        $this->assertSelectorTextContains('div.alert.alert-danger', 'Invalid credentials.');
    }

    public function testLoginIncorrectPassword(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $form = $crawler->selectButton('Submit')->form();
        $form['_username']->setValue($_ENV['ADMIN_LOGIN']);
        $form['_password']->setValue('bad_password');

        $client->submit($form);
        $this->assertResponseRedirects('/login');
        $crawler = $client->followRedirect();
        $this->assertResponseIsSuccessful();

        $this->assertSelectorTextContains('div.alert.alert-danger', 'Invalid credentials.');
    }

    /**
     * The login throttling budget is keyed on the username *and* the client IP, and it outlives
     * the kernel because it lives in a cache pool. Giving each test its own random IP keeps one
     * test from spending another's budget, including across repeated local runs.
     */
    private function submitLogin($client, string $username, string $password): void
    {
        $crawler = $client->request('GET', '/login');

        $form = $crawler->selectButton('Submit')->form();
        $form['_username']->setValue($username);
        $form['_password']->setValue($password);

        $client->submit($form);
    }

    public function testRepeatedFailedLoginsAreThrottled(): void
    {
        $client = static::createClient();
        $client->setServerParameter('REMOTE_ADDR', '10.'.random_int(0, 255).'.'.random_int(0, 255).'.'.random_int(1, 254));

        for ($i = 1; $i <= 5; ++$i) {
            $this->submitLogin($client, $_ENV['ADMIN_LOGIN'], 'bad_password');
            $client->followRedirect();
            $this->assertSelectorTextContains('div.alert.alert-danger', 'Invalid credentials.', 'attempt '.$i.' should still be answered normally');
        }

        $this->submitLogin($client, $_ENV['ADMIN_LOGIN'], 'bad_password');
        $client->followRedirect();

        $this->assertSelectorTextContains('div.alert.alert-danger', 'Too many failed login attempts');
    }

    /**
     * The budget is per username and IP, so a few typos must not lock the admin out.
     */
    public function testACorrectLoginStillWorksAfterAFewFailedAttempts(): void
    {
        $client = static::createClient();
        $client->setServerParameter('REMOTE_ADDR', '10.'.random_int(0, 255).'.'.random_int(0, 255).'.'.random_int(1, 254));

        for ($i = 1; $i <= 4; ++$i) {
            $this->submitLogin($client, $_ENV['ADMIN_LOGIN'], 'bad_password');
        }

        $this->submitLogin($client, $_ENV['ADMIN_LOGIN'], $_ENV['ADMIN_PASSWORD']);

        $this->assertResponseRedirects('/dashboard');
    }

    public function testLoginCorrect(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $form = $crawler->selectButton('Submit')->form();
        $form['_username']->setValue($_ENV['ADMIN_LOGIN']);
        $form['_password']->setValue($_ENV['ADMIN_PASSWORD']);

        $client->submit($form);
        $this->assertResponseRedirects('/dashboard');
        $crawler = $client->followRedirect();
        $this->assertResponseIsSuccessful();

        $this->assertSelectorTextContains('h1', 'Dashboard');
        $this->assertSelectorTextContains('h3.capabilities', 'Capabilities');
        $this->assertSelectorTextContains('h3.objects', 'Objects');
        $this->assertSelectorTextContains('h3.health', 'Health');
        $this->assertSelectorExists('nav.navbar');
    }

    /**
     * Logging out is a state change: a bare `GET /logout` must not end the session.
     */
    public function testLogoutRequiresACsrfToken(): void
    {
        $client = static::createClient();
        $client->loginUser(new AdminUser('admin', 'test'));

        $client->request('GET', '/logout');
        $this->assertResponseStatusCodeSame(403);

        $client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful('The session must survive a logout without a token');
    }

    public function testTheDiagnosticsPageListsEveryBucket(): void
    {
        $client = static::createClient();
        $client->loginUser(new AdminUser('admin', 'test'));

        $client->request('GET', '/dashboard/diagnostics');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Diagnostics');

        foreach (['Runtime', 'Database', 'Authentication', 'Scheduling and mail', 'Endpoints'] as $bucket) {
            $this->assertAnySelectorTextContains('h3', $bucket);
        }

        // Nothing on this page may disclose the mailer credentials or the application secret
        $content = $client->getResponse()->getContent();
        $this->assertStringNotContainsString($_ENV['APP_SECRET'], $content);
        $this->assertStringNotContainsString('MAILER_DSN', $content);
    }

    public function testTheDashboardPointsAtTheDiagnosticsPage(): void
    {
        $client = static::createClient();
        $client->loginUser(new AdminUser('admin', 'test'));

        $crawler = $client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a[href="/dashboard/diagnostics"]');

        $client->click($crawler->filter('a[href="/dashboard/diagnostics"]')->link());
        $this->assertResponseIsSuccessful();
    }

    public function testTheDiagnosticsPageIsNotReachableAnonymously(): void
    {
        $client = static::createClient();

        $client->request('GET', '/dashboard/diagnostics');

        $this->assertResponseRedirects('/login');
    }

    /**
     * The admin interface is protected by a terminal `^/` rule, so the endpoints that have to stay
     * reachable without an account are the ones listed before it. If that list ever falls after the
     * catch-all, clients can no longer discover or reach the DAV endpoint and nobody can log in.
     */
    public function testThePublicEndpointsStayPublic(): void
    {
        $client = static::createClient();

        foreach (['/', '/login', '/.well-known/caldav', '/.well-known/carddav'] as $url) {
            $client->request('GET', $url);

            $this->assertNotSame(
                '/login',
                $client->getResponse()->headers->get('Location'),
                $url.' must not redirect to the login page'
            );
        }
    }

    public function testLogoutWorksFromTheMenuLink(): void
    {
        $client = static::createClient();
        $client->loginUser(new AdminUser('admin', 'test'));

        $crawler = $client->request('GET', '/dashboard');
        $client->click($crawler->filter('a.dropdown-item')->selectLink('Logout')->link());

        $client->request('GET', '/dashboard');
        $this->assertResponseRedirects('/login');
    }
}
