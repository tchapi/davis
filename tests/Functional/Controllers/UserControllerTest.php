<?php

namespace App\Tests\Functional;

use App\Entity\Principal;
use App\Entity\User;
use App\Security\AdminUser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UserControllerTest extends WebTestCase
{
    use AdminPostTrait;

    private function getUserId($client, string $username): int
    {
        $userRepository = static::getContainer()->get('doctrine.orm.entity_manager')->getRepository(User::class);
        $user = $userRepository->findOneByUsername($username);

        return $user->getId();
    }

    public function testUserIndex(): void
    {
        $user = new AdminUser('admin', 'test');

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('GET', '/users/');

        $this->assertResponseIsSuccessful();

        $this->assertSelectorExists('nav.navbar');
        $this->assertSelectorTextContains('h1', 'Users and Resources');
        $this->assertSelectorTextContains('a.btn', '+ New User');
        $this->assertAnySelectorTextContains('h5', 'Test User');
    }

    public function testUserEdit(): void
    {
        $user = new AdminUser('admin', 'test');

        $client = static::createClient();
        $client->loginUser($user);

        $userId = $this->getUserId($client, 'test_user');

        $client->request('GET', '/users/edit/'.$userId);

        $this->assertResponseIsSuccessful();

        $this->assertSelectorTextContains('h1', 'Editing User «test_user»');
        $this->assertSelectorTextContains('button#user_save', 'Save');

        $client->submitForm('user_save');

        $this->assertResponseRedirects('/users/');
        $client->followRedirect();

        $this->assertAnySelectorTextContains('h5', 'Test User');
    }

    public function testUserNew(): void
    {
        $user = new AdminUser('admin', 'test');

        $client = static::createClient();
        $client->loginUser($user);
        $crawler = $client->request('GET', '/users/new');

        $this->assertResponseIsSuccessful();

        $this->assertSelectorTextContains('h1', 'New User');
        $this->assertSelectorTextContains('button#user_save', 'Save');

        $buttonCrawlerNode = $crawler->selectButton('user_save');

        $form = $buttonCrawlerNode->form();
        $client->submit($form, [
            'user[username]' => 'new_test_user',
            'user[displayName]' => 'New test User',
            'user[email]' => 'coucou@coucou.com',
            'user[password][first]' => 'coucou',
            'user[password][second]' => 'coucou',
            'user[isAdmin]' => false,
        ]);

        $this->assertResponseRedirects('/users/');
        $client->followRedirect();

        $this->assertAnySelectorTextContains('h5', 'Test User');
        $this->assertAnySelectorTextContains('h5', 'New test User');
    }

    public function testUserDelete(): void
    {
        $user = new AdminUser('admin', 'test');

        $client = static::createClient();
        $client->loginUser($user);

        $userId1 = $this->getUserId($client, 'test_user');
        $userId2 = $this->getUserId($client, 'test_user2');

        $this->postAdmin($client, '/users/delete/'.$userId1);

        $this->assertResponseRedirects('/users/');
        $client->followRedirect();

        $this->assertAnySelectorTextContains('h5', 'Test User 2');

        $this->postAdmin($client, '/users/delete/'.$userId2);

        $this->assertResponseRedirects('/users/');
        $client->followRedirect();

        $this->assertSelectorTextContains('div#no-user', 'No users yet.');
    }

    public function testUserDelegates(): void
    {
        $user = new AdminUser('admin', 'test');

        $client = static::createClient();
        $client->loginUser($user);

        $userId = $this->getUserId($client, 'test_user');

        $client->request('GET', '/users/delegates/'.$userId);

        $this->assertResponseIsSuccessful();

        $this->assertSelectorExists('nav.navbar');
        $this->assertSelectorTextContains('h1', 'Delegates for Test User');
        $this->assertSelectorTextContains('a.btn', '+ Add a delegate');
        $this->assertAnySelectorTextContains('div', 'Delegation is enabled for this account.');
        $this->assertAnySelectorTextContains('button.btn', 'Disable it');

        $client->submitForm('Disable it');

        $this->assertResponseRedirects('/users/delegates/'.$userId);
        $client->followRedirect();

        $this->assertSelectorExists('nav.navbar');
        $this->assertSelectorTextContains('h1', 'Delegates for Test User');
        $this->assertSelectorTextNotContains('a.btn', '+ Add a delegate');
        $this->assertAnySelectorTextContains('div', 'Delegation is not enabled for this account.');
        $this->assertAnySelectorTextContains('button.btn', 'Enable it');
    }

    public function testUserDelegateAddAndRemove(): void
    {
        $user = new AdminUser('admin', 'test');

        $client = static::createClient();
        $client->loginUser($user);

        $userId = $this->getUserId($client, 'test_user');
        $principalRepository = static::getContainer()->get('doctrine.orm.entity_manager')->getRepository(Principal::class);
        $delegate = $principalRepository->findOneByUri(Principal::PREFIX.'test_user2');
        $writeProxy = $principalRepository->findOneByUri(Principal::PREFIX.'test_user'.Principal::WRITE_PROXY_SUFFIX);

        $this->postAdmin($client, '/users/delegates/'.$userId.'/add', ['principalId' => $delegate->getId(), 'write' => 'true']);

        $this->assertResponseRedirects('/users/delegates/'.$userId);
        $client->followRedirect();
        $this->assertAnySelectorTextContains('h5', 'Test User 2');

        $this->postAdmin($client, '/users/delegates/'.$userId.'/remove/'.$writeProxy->getId().'/'.$delegate->getId());

        $this->assertResponseRedirects('/users/delegates/'.$userId);
        $client->followRedirect();
        $this->assertSelectorTextNotContains('h5', 'Test User 2');
    }

    public function testStateChangingRoutesRejectGet(): void
    {
        $user = new AdminUser('admin', 'test');

        $client = static::createClient();
        $client->loginUser($user);

        $userId = $this->getUserId($client, 'test_user');

        foreach ([
            '/users/delete/'.$userId,
            '/users/delegation/'.$userId.'/off',
            '/users/delegates/'.$userId.'/add?principalId=1&write=true',
            '/users/delegates/'.$userId.'/remove/1/2',
        ] as $url) {
            $client->request('GET', $url);
            $this->assertResponseStatusCodeSame(405, 'GET '.$url.' must not be allowed');
        }

        // Nothing was deleted
        $this->assertNotNull(static::getContainer()->get('doctrine.orm.entity_manager')->getRepository(User::class)->findOneByUsername('test_user'));
    }

    public function testUserDeleteRejectsInvalidCsrfToken(): void
    {
        $user = new AdminUser('admin', 'test');

        $client = static::createClient();
        $client->loginUser($user);

        $userId = $this->getUserId($client, 'test_user');

        $client->request('POST', '/users/delete/'.$userId, ['_token' => 'not-the-token']);
        $this->assertResponseStatusCodeSame(403);

        $client->request('POST', '/users/delete/'.$userId);
        $this->assertResponseStatusCodeSame(403);

        $this->assertNotNull(static::getContainer()->get('doctrine.orm.entity_manager')->getRepository(User::class)->findOneByUsername('test_user'));
    }
}
