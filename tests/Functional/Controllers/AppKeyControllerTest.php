<?php

namespace App\Tests\Functional;

use App\Entity\AppKey;
use App\Entity\User;
use App\Repository\AppKeyRepository;
use App\Security\AdminUser;
use App\Services\BasicAuth;
use Sabre\HTTP\Request;
use Sabre\HTTP\Response;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AppKeyControllerTest extends WebTestCase
{
    use AdminPostTrait;

    private function adminClient(): KernelBrowser
    {
        $client = static::createClient();
        $client->loginUser(new AdminUser('admin', 'test'));

        return $client;
    }

    private static function basicAuth(string $userPass): array
    {
        $request = new Request('PROPFIND', '/dav/', ['Authorization' => 'Basic '.base64_encode($userPass)]);

        return static::getContainer()->get(BasicAuth::class)->check($request, new Response());
    }

    private static function keyByAppId(string $appId): ?AppKey
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $em->clear();

        return $em->getRepository(AppKey::class)->findOneByAppId($appId);
    }

    public function testIndexIsNotReachableAnonymously(): void
    {
        $client = static::createClient();
        $client->request('GET', '/app-keys/');

        $this->assertResponseRedirects('/login');
    }

    public function testIndexListsTheKeys(): void
    {
        $client = $this->adminClient();
        $client->request('GET', '/app-keys/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'App keys');
        $this->assertSelectorTextContains('a.btn', '+ New app key');
        $this->assertAnySelectorTextContains('h5', 'Test app');
        $this->assertAnySelectorTextContains('h5', 'Revoked app');
        $this->assertAnySelectorTextContains('code', 'app_test');
        $this->assertAnySelectorTextContains('code', 'app_revoked');
        $this->assertAnySelectorTextContains('.badge', 'Active');
        $this->assertAnySelectorTextContains('.badge', 'Revoked on');
        $this->assertSelectorNotExists('#app-key-secret');

        $content = $client->getResponse()->getContent();
        $this->assertStringNotContainsString('app-secret', $content);
        $this->assertStringNotContainsString('$2y$', $content);
    }

    public function testTheNavigationLinksToTheAppKeys(): void
    {
        $client = $this->adminClient();
        $client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a[href="/app-keys/"]');
    }

    public function testCreateShowsTheSecretExactlyOnce(): void
    {
        $client = $this->adminClient();
        $crawler = $client->request('GET', '/app-keys/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'New app key');
        $this->assertSelectorExists('button#app_key_save');

        $client->submit($crawler->selectButton('app_key_save')->form(), [
            'app_key[name]' => 'cal.com',
            'app_key[scope]' => "test_user\ntest_user2",
        ]);

        $this->assertResponseRedirects('/app-keys/');
        $crawler = $client->followRedirect();

        $this->assertSelectorExists('#app-key-secret');
        $appId = $crawler->filter('code.app-id')->text();
        $secret = $crawler->filter('code.app-secret')->text();
        $this->assertMatchesRegularExpression('/^app_[0-9a-f]{24}$/', $appId);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $secret);

        $this->assertSame(['test_user', 'test_user2'], self::keyByAppId($appId)->getScope());

        [$ok, $principal] = self::basicAuth('test_user*'.$appId.':'.$secret);
        $this->assertTrue($ok);
        $this->assertSame('principals/test_user', $principal);

        [$ok] = self::basicAuth('alice*'.$appId.':'.$secret);
        $this->assertFalse($ok, 'alice is not in the scope');

        $client->request('GET', '/app-keys/');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('#app-key-secret');
        $this->assertStringNotContainsString($secret, $client->getResponse()->getContent());
    }

    public function testCreateRequiresAName(): void
    {
        $client = $this->adminClient();
        $crawler = $client->request('GET', '/app-keys/new');
        $before = count(static::getContainer()->get(AppKeyRepository::class)->findAll());

        $client->submit($crawler->selectButton('app_key_save')->form(), [
            'app_key[name]' => '',
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertSelectorExists('.invalid-feedback');
        $this->assertCount($before, static::getContainer()->get(AppKeyRepository::class)->findAll());
    }

    public function testCreateRequiresAScope(): void
    {
        $client = $this->adminClient();
        $before = count(static::getContainer()->get(AppKeyRepository::class)->findAll());

        foreach (['', " ,\n"] as $scope) {
            $crawler = $client->request('GET', '/app-keys/new');
            $client->submit($crawler->selectButton('app_key_save')->form(), [
                'app_key[name]' => 'nobody',
                'app_key[scope]' => $scope,
            ]);

            $this->assertResponseStatusCodeSame(200);
            $this->assertSelectorTextContains('.invalid-feedback', 'Enter at least one username');
        }

        $this->assertCount($before, static::getContainer()->get(AppKeyRepository::class)->findAll());
    }

    public function testCreateRejectsUnknownUsers(): void
    {
        $client = $this->adminClient();
        $before = count(static::getContainer()->get(AppKeyRepository::class)->findAll());

        $crawler = $client->request('GET', '/app-keys/new');
        $client->submit($crawler->selectButton('app_key_save')->form(), [
            'app_key[name]' => 'webmail',
            'app_key[scope]' => "*\ntest_user\nalice",
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertSelectorTextContains('.invalid-feedback', 'These users do not exist: alice.');
        $this->assertCount($before, static::getContainer()->get(AppKeyRepository::class)->findAll());
    }

    public function testIndexShowsWhoTheKeyActedAs(): void
    {
        $client = $this->adminClient();

        [$ok] = self::basicAuth('test_user2*app_test:app-secret');
        $this->assertTrue($ok);

        $client->request('GET', '/app-keys/');

        $this->assertResponseIsSuccessful();
        $this->assertAnySelectorTextContains('p', 'Used as : test_user2');
    }

    public function testDeletingAUserUpdatesTheKeys(): void
    {
        $client = $this->adminClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        $onlyUser2 = AppKey::create('only user 2', ['test_user2'])->setSecretHash('x');
        $user2AndBob = AppKey::create('user 2 and bob', ['test_user2', 'bob'])->setSecretHash('x');
        $em->persist($onlyUser2);
        $em->persist($user2AndBob);
        $em->flush();

        $userId = $em->getRepository(User::class)->findOneByUsername('test_user2')->getId();
        $this->postAdmin($client, '/users/delete/'.$userId);
        $this->assertResponseRedirects('/users/');

        $onlyUser2 = self::keyByAppId($onlyUser2->getAppId());
        $this->assertNotNull($onlyUser2->getRevokedAt(), 'A key only for the deleted user is revoked');

        $user2AndBob = self::keyByAppId($user2AndBob->getAppId());
        $this->assertNull($user2AndBob->getRevokedAt());
        $this->assertSame(['bob'], $user2AndBob->getScope());

        $this->assertNull(self::keyByAppId('app_test')->getRevokedAt(), 'A * key is not changed');
    }

    public function testCreateWithAnExpiry(): void
    {
        $client = $this->adminClient();
        $crawler = $client->request('GET', '/app-keys/new');

        $client->submit($crawler->selectButton('app_key_save')->form(), [
            'app_key[name]' => 'temporary',
            'app_key[scope]' => '*',
            'app_key[expiresAt]' => '2099-01-01',
        ]);

        $this->assertResponseRedirects('/app-keys/');
        $client->followRedirect();

        $this->assertAnySelectorTextContains('.badge', 'Expires on 2099-01-01 00:00');
    }

    public function testCreateWithAnEmptyExpiryNeverExpires(): void
    {
        $client = $this->adminClient();
        $crawler = $client->request('GET', '/app-keys/new');

        $this->assertSelectorExists('button[data-clear-target="app_key_expiresAt"]');

        $client->submit($crawler->selectButton('app_key_save')->form(), [
            'app_key[name]' => 'forever',
            'app_key[scope]' => '*',
            'app_key[expiresAt]' => '',
        ]);

        $this->assertResponseRedirects('/app-keys/');
        $crawler = $client->followRedirect();

        $appId = $crawler->filter('code.app-id')->text();
        $this->assertNull(self::keyByAppId($appId)->getRevokedAt());
    }

    public function testRevoke(): void
    {
        $client = $this->adminClient();
        $id = self::keyByAppId('app_test')->getId();

        $this->postAdmin($client, '/app-keys/revoke/'.$id);

        $this->assertResponseRedirects('/app-keys/');
        $client->followRedirect();
        $this->assertAnySelectorTextContains('.badge', 'Revoked on');
        $this->assertNotNull(self::keyByAppId('app_test')->getRevokedAt());

        [$ok] = self::basicAuth('test_user2*app_test:app-secret');
        $this->assertFalse($ok);
    }

    public function testRevokeRequiresACsrfToken(): void
    {
        $client = $this->adminClient();
        $id = self::keyByAppId('app_test')->getId();

        $client->request('POST', '/app-keys/revoke/'.$id);

        $this->assertResponseStatusCodeSame(403);
        $this->assertNull(self::keyByAppId('app_test')->getRevokedAt());
    }
}
