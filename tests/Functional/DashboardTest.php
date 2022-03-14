<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DashboardTest extends WebTestCase
{
    public function testIndexPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h3', 'Davis v');

        $this->assertSelectorExists('div.caldav');
        $this->assertSelectorExists('div.carddav');
        $this->assertSelectorExists('div.webdav');
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
        $form['username']->setValue('bad_'.$_ENV['ADMIN_LOGIN']);
        $form['password']->setValue('bad_password');

        $client->submit($form);
        $this->assertResponseRedirects('/login');
        $crawler = $client->followRedirect();
        $this->assertResponseIsSuccessful();

        $this->assertSelectorTextContains('div.alert.alert-danger', 'Username could not be found.');
    }

    public function testLoginIncorrectPassword(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $form = $crawler->selectButton('Submit')->form();
        $form['username']->setValue($_ENV['ADMIN_LOGIN']);
        $form['password']->setValue('bad_password');

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
        $form['username']->setValue($_ENV['ADMIN_LOGIN']);
        $form['password']->setValue($_ENV['ADMIN_PASSWORD']);

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
}
