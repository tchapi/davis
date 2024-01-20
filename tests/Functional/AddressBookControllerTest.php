<?php

namespace App\Tests\Functional;

use App\Entity\AddressBook;
use App\Security\AdminUser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AddressBookControllerTest extends WebTestCase
{
    public function testAddressBookIndex(): void
    {
        $user = new AdminUser('admin', 'test');

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('GET', '/addressbooks/test_user');

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

        $addressbookRepository = static::getContainer()->get('doctrine.orm.entity_manager')->getRepository(AddressBook::class);
        $addressbook = $addressbookRepository->findOneByDisplayName('default.addressbook.title');

        $client->request('GET', '/addressbooks/test_user/edit/'.$addressbook->getId());

        $this->assertResponseIsSuccessful();

        $this->assertSelectorTextContains('h1', 'Editing Address Book «default.addressbook.title»');
        $this->assertSelectorTextContains('button#address_book_save', 'Save');

        $client->submitForm('address_book_save');

        $this->assertResponseRedirects('/addressbooks/test_user');
        $client->followRedirect();

        $this->assertSelectorTextContains('h5', 'default.addressbook.title');
    }

    public function testAddressBookNew(): void
    {
        $user = new AdminUser('admin', 'test');

        $client = static::createClient();
        $client->loginUser($user);
        $crawler = $client->request('GET', '/addressbooks/test_user/new');

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

        $this->assertResponseRedirects('/addressbooks/test_user');
        $client->followRedirect();

        $this->assertSelectorTextContains('h5', 'default.addressbook.title');
        $this->assertAnySelectorTextContains('h5', 'New test address book');
    }

    public function testAddressBookDelete(): void
    {
        $user = new AdminUser('admin', 'test');

        $client = static::createClient();
        $client->loginUser($user);

        $addressbookRepository = static::getContainer()->get('doctrine.orm.entity_manager')->getRepository(AddressBook::class);
        $addressbook = $addressbookRepository->findOneByDisplayName('default.addressbook.title');

        $client->request('GET', '/addressbooks/test_user/delete/'.$addressbook->getId());

        $this->assertResponseRedirects('/addressbooks/test_user');
        $client->followRedirect();

        $this->assertSelectorTextNotContains('h5', 'default.addressbook.title');
    }
}
