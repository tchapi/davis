<?php

namespace App\Tests\Functional;

use App\Entity\AddressBook;
use App\Entity\User;
use App\Security\AdminUser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AddressBookControllerTest extends WebTestCase
{
    use AdminPostTrait;

    private function getUserId($client, string $username): int
    {
        $userRepository = static::getContainer()->get('doctrine.orm.entity_manager')->getRepository(User::class);
        $user = $userRepository->findOneByUsername($username);

        return $user->getId();
    }

    public function testAddressBookIndex(): void
    {
        $user = new AdminUser('admin', 'test');

        $client = static::createClient();
        $client->loginUser($user);

        $userId = $this->getUserId($client, 'test_user');

        $client->request('GET', '/addressbooks/'.$userId);

        $this->assertResponseIsSuccessful();

        $this->assertSelectorExists('nav.navbar');
        $this->assertSelectorTextContains('h1', 'Address books for Test User');
        $this->assertSelectorTextContains('a.btn', '+ New Address Book');
        $this->assertSelectorTextContains('h5', 'default.addressbook.title');
    }

    public function testAddressBookEdit(): void
    {
        $user = new AdminUser('admin', 'test');

        $client = static::createClient();
        $client->loginUser($user);

        $userId = $this->getUserId($client, 'test_user');

        $addressbookRepository = static::getContainer()->get('doctrine.orm.entity_manager')->getRepository(AddressBook::class);
        $addressbook = $addressbookRepository->findOneByDisplayName('default.addressbook.title');

        $client->request('GET', '/addressbooks/'.$userId.'/edit/'.$addressbook->getId());

        $this->assertResponseIsSuccessful();

        $this->assertSelectorTextContains('h1', 'Editing Address Book «default.addressbook.title»');
        $this->assertSelectorTextContains('button#address_book_save', 'Save');

        $client->submitForm('address_book_save');

        $this->assertResponseRedirects('/addressbooks/'.$userId);
        $client->followRedirect();

        $this->assertSelectorTextContains('h5', 'default.addressbook.title');
    }

    public function testAddressBookNew(): void
    {
        $user = new AdminUser('admin', 'test');

        $client = static::createClient();
        $client->loginUser($user);

        $userId = $this->getUserId($client, 'test_user');

        $crawler = $client->request('GET', '/addressbooks/'.$userId.'/new');

        $this->assertResponseIsSuccessful();

        $this->assertSelectorTextContains('h1', 'New Address Book ');
        $this->assertSelectorTextContains('button#address_book_save', 'Save');

        $buttonCrawlerNode = $crawler->selectButton('address_book_save');

        $form = $buttonCrawlerNode->form();
        $client->submit($form, [
            'address_book[uri]' => 'new_test_address_book',
            'address_book[displayName]' => 'New test address book',
            'address_book[description]' => 'new address book',
        ]);

        $this->assertResponseRedirects('/addressbooks/'.$userId);
        $client->followRedirect();

        $this->assertSelectorTextContains('h5', 'default.addressbook.title');
        $this->assertAnySelectorTextContains('h5', 'New test address book');
    }

    public function testAddressBookDelete(): void
    {
        $user = new AdminUser('admin', 'test');

        $client = static::createClient();
        $client->loginUser($user);

        $userId = $this->getUserId($client, 'test_user');

        $addressbookRepository = static::getContainer()->get('doctrine.orm.entity_manager')->getRepository(AddressBook::class);
        $addressbook = $addressbookRepository->findOneByDisplayName('default.addressbook.title');

        $this->postAdmin($client, '/addressbooks/'.$userId.'/delete/'.$addressbook->getId());

        $this->assertResponseRedirects('/addressbooks/'.$userId);
        $client->followRedirect();

        $this->assertSelectorTextNotContains('h5', 'default.addressbook.title');
    }

    public function testAddressBookDeleteRejectsGetAndInvalidCsrfToken(): void
    {
        $user = new AdminUser('admin', 'test');

        $client = static::createClient();
        $client->loginUser($user);

        $userId = $this->getUserId($client, 'test_user');

        $addressbookRepository = static::getContainer()->get('doctrine.orm.entity_manager')->getRepository(AddressBook::class);
        $addressbook = $addressbookRepository->findOneByDisplayName('default.addressbook.title');

        $client->request('GET', '/addressbooks/'.$userId.'/delete/'.$addressbook->getId());
        $this->assertResponseStatusCodeSame(405);

        $client->request('POST', '/addressbooks/'.$userId.'/delete/'.$addressbook->getId(), ['_token' => 'not-the-token']);
        $this->assertResponseStatusCodeSame(403);

        $this->assertNotNull($addressbookRepository->find($addressbook->getId()));
    }

    public function testAddressBookActionsOnAnotherUsersBookAre404(): void
    {
        $user = new AdminUser('admin', 'test');

        $client = static::createClient();
        $client->loginUser($user);

        $otherId = $this->getUserId($client, 'test_user2');

        $addressbookRepository = static::getContainer()->get('doctrine.orm.entity_manager')->getRepository(AddressBook::class);
        $addressbook = $addressbookRepository->findOneByDisplayName('default.addressbook.title');

        $client->request('GET', '/addressbooks/'.$otherId.'/edit/'.$addressbook->getId());
        $this->assertResponseStatusCodeSame(404);

        $this->postAdmin($client, '/addressbooks/'.$otherId.'/delete/'.$addressbook->getId());
        $this->assertResponseStatusCodeSame(404);

        $this->assertNotNull($addressbookRepository->find($addressbook->getId()));
    }

    public function testAddressBookNewIgnoresASubmittedOwner(): void
    {
        $user = new AdminUser('admin', 'test');

        $client = static::createClient();
        $client->loginUser($user);

        $userId = $this->getUserId($client, 'test_user');

        $crawler = $client->request('GET', '/addressbooks/'.$userId.'/new');
        $this->assertSelectorNotExists('input[name="address_book[principalUri]"]');

        $form = $crawler->selectButton('address_book_save')->form();
        $values = $form->getPhpValues();
        $values['address_book']['uri'] = 'hijack';
        $values['address_book']['displayName'] = 'Hijack';
        $values['address_book']['principalUri'] = 'principals/test_user2';

        $client->request($form->getMethod(), $form->getUri(), $values);

        $this->assertResponseIsSuccessful();
        $addressbookRepository = static::getContainer()->get('doctrine.orm.entity_manager')->getRepository(AddressBook::class);
        $this->assertNull($addressbookRepository->findOneBy(['uri' => 'hijack']));
    }
}
