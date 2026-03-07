<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Repository\CalendarInstanceRepository;
use App\Security\AdminUser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CalendarControllerTest extends WebTestCase
{
    private function getUserId($client, string $username): int
    {
        $userRepository = static::getContainer()->get('doctrine.orm.entity_manager')->getRepository(User::class);
        $user = $userRepository->findOneByUsername($username);

        return $user->getId();
    }

    public function testCalendarIndex(): void
    {
        $user = new AdminUser('admin', 'test');

        $client = static::createClient();
        $client->loginUser($user);

        $userId = $this->getUserId($client, 'test_user');

        $client->request('GET', '/calendars/'.$userId);

        $this->assertResponseIsSuccessful();

        $this->assertSelectorExists('nav.navbar');
        $this->assertSelectorTextContains('h1', 'Calendars for Test User');
        $this->assertSelectorTextContains('a.btn', '+ New Calendar');
        $this->assertSelectorTextContains('h5', 'default.calendar.title');
    }

    public function testCalendarEdit(): void
    {
        $user = new AdminUser('admin', 'test');

        $client = static::createClient();
        $client->loginUser($user);

        $userId = $this->getUserId($client, 'test_user');

        $calendarRepository = static::getContainer()->get(CalendarInstanceRepository::class);
        $calendar = $calendarRepository->findOneByDisplayName('default.calendar.title');

        $client->request('GET', '/calendars/'.$userId.'/edit/'.$calendar->getId());

        $this->assertResponseIsSuccessful();

        $this->assertSelectorTextContains('h1', 'Editing Calendar «default.calendar.title»');
        $this->assertSelectorTextContains('button#calendar_instance_save', 'Save');

        $client->submitForm('calendar_instance_save');

        $this->assertResponseRedirects('/calendars/'.$userId);
        $client->followRedirect();

        $this->assertSelectorTextContains('h5', 'default.calendar.title');
    }

    public function testCalendarNew(): void
    {
        $user = new AdminUser('admin', 'test');

        $client = static::createClient();
        $client->loginUser($user);

        $userId = $this->getUserId($client, 'test_user');

        $crawler = $client->request('GET', '/calendars/'.$userId.'/new');

        $this->assertResponseIsSuccessful();

        $this->assertSelectorTextContains('h1', 'New Calendar ');
        $this->assertSelectorTextContains('button#calendar_instance_save', 'Save');

        $buttonCrawlerNode = $crawler->selectButton('calendar_instance_save');

        $form = $buttonCrawlerNode->form();
        $client->submit($form, [
            'calendar_instance[uri]' => 'new_test_calendar',
            'calendar_instance[displayName]' => 'New test calendar',
            'calendar_instance[description]' => 'new calendar',
            'calendar_instance[calendarColor]' => '#00112233',
        ]);

        $this->assertResponseRedirects('/calendars/'.$userId);
        $client->followRedirect();

        $this->assertSelectorTextContains('h5', 'default.calendar.title');
        $this->assertAnySelectorTextContains('h5', 'New test calendar');
    }

    public function testCalendarDelete(): void
    {
        $user = new AdminUser('admin', 'test');

        $client = static::createClient();
        $client->loginUser($user);

        $userId = $this->getUserId($client, 'test_user');

        $calendarRepository = static::getContainer()->get(CalendarInstanceRepository::class);
        $calendar = $calendarRepository->findOneByDisplayName('default.calendar.title');

        $client->request('GET', '/calendars/'.$userId.'/delete/'.$calendar->getId());

        $this->assertResponseRedirects('/calendars/'.$userId);
        $client->followRedirect();

        $this->assertSelectorTextNotContains('h5', 'default.calendar.title');
    }
}
