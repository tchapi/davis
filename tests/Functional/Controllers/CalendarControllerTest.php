<?php

namespace App\Tests\Functional;

use App\Entity\Principal;
use App\Entity\User;
use App\Repository\CalendarInstanceRepository;
use App\Security\AdminUser;
use Sabre\DAV\Sharing\Plugin as SharingPlugin;
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

        $crawler = $client->request('GET', '/calendars/'.$userId);
        $csrfToken = $crawler->filter('#deleteModal-calendars-form input[name="_token"]')->attr('value');

        $client->request('POST', '/calendars/'.$userId.'/delete/'.$calendar->getId(), [
            '_token' => $csrfToken,
        ]);

        $this->assertResponseRedirects('/calendars/'.$userId);
        $client->followRedirect();

        $this->assertSelectorTextNotContains('h5', 'default.calendar.title');
    }

    public function testCalendarSharingAndRevocation(): void
    {
        $client = static::createClient();
        $client->loginUser(new AdminUser('admin', 'test'));

        $userId = $this->getUserId($client, 'test_user');
        $calendarRepository = static::getContainer()->get(CalendarInstanceRepository::class);
        $calendar = $calendarRepository->findOneByDisplayName('default.calendar.title');
        $sharee = static::getContainer()->get('doctrine.orm.entity_manager')->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.'test_user2');

        $crawler = $client->request('GET', '/calendars/'.$userId);
        $shareToken = $crawler->filter('#shareModal-addForm input[name="_token"]')->attr('value');
        $revokeToken = $crawler->filter('#shareModal-shareeTemplate input[name="_token"]')->attr('value');

        $client->request('POST', '/calendars/'.$userId.'/share/'.$calendar->getId(), [
            '_token' => $shareToken,
            'principalId' => $sharee->getId(),
            'write' => 'true',
        ]);
        $this->assertResponseRedirects('/calendars/'.$userId);

        $calendarRepository = static::getContainer()->get(CalendarInstanceRepository::class);
        $sharedInstance = $calendarRepository->findSharedInstanceOfInstanceFor($calendar->getCalendar()->getId(), $sharee->getUri());
        $this->assertNotNull($sharedInstance);
        $this->assertSame(SharingPlugin::ACCESS_READWRITE, $sharedInstance->getAccess());

        $sharedInstanceId = $sharedInstance->getId();
        $client->request('POST', '/calendars/'.$userId.'/revoke/'.$sharedInstanceId, [
            '_token' => $revokeToken,
        ]);
        $this->assertResponseRedirects('/calendars/'.$userId);

        $calendarRepository = static::getContainer()->get(CalendarInstanceRepository::class);
        $this->assertNull($calendarRepository->find($sharedInstanceId));
    }
}
