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
        $this->assertSelectorTextContains('h3.environment', 'Configured environment');
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
