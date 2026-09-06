<?php

namespace App\Tests\Functional;

use App\Entity\Calendar;
use App\Entity\CalendarInstance;
use App\Entity\CalendarObject;
use App\Entity\Principal;
use App\Entity\User;
use App\Repository\CalendarInstanceRepository;
use App\Security\AdminUser;
use Sabre\DAV\Sharing\Plugin as SharingPlugin;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CalendarControllerTest extends WebTestCase
{
    use AdminPostTrait;

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

        $this->postAdmin($client, '/calendars/'.$userId.'/delete/'.$calendar->getId());

        $this->assertResponseRedirects('/calendars/'.$userId);
        $client->followRedirect();

        $this->assertSelectorTextNotContains('h5', 'default.calendar.title');
    }

    public function testCalendarShareAddAndRevoke(): void
    {
        $user = new AdminUser('admin', 'test');

        $client = static::createClient();
        $client->loginUser($user);

        $userId = $this->getUserId($client, 'test_user');

        $calendarRepository = static::getContainer()->get(CalendarInstanceRepository::class);
        $calendar = $calendarRepository->findOneByDisplayName('default.calendar.title');
        $sharee = static::getContainer()->get('doctrine.orm.entity_manager')->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.'test_user2');

        $this->postAdmin($client, '/calendars/'.$userId.'/share/'.$calendar->getId(), ['principalId' => $sharee->getId(), 'write' => 'true']);
        $this->assertResponseRedirects('/calendars/'.$userId);

        $client->request('GET', '/calendars/'.$userId.'/shares/'.$calendar->getCalendar()->getId());
        $this->assertResponseIsSuccessful();
        $shares = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $shares);
        $this->assertSame(Principal::PREFIX.'test_user2', $shares[0]['principalUri']);
        $this->assertTrue($shares[0]['isWriteAccess']);

        $this->postAdmin($client, $shares[0]['revokeUrl']);
        $this->assertResponseRedirects('/calendars/'.$userId);

        $client->request('GET', '/calendars/'.$userId.'/shares/'.$calendar->getCalendar()->getId());
        $this->assertSame([], json_decode($client->getResponse()->getContent(), true));
    }

    public function testStateChangingRoutesRejectGet(): void
    {
        $user = new AdminUser('admin', 'test');

        $client = static::createClient();
        $client->loginUser($user);

        $userId = $this->getUserId($client, 'test_user');
        $calendarRepository = static::getContainer()->get(CalendarInstanceRepository::class);
        $calendar = $calendarRepository->findOneByDisplayName('default.calendar.title');

        foreach ([
            '/calendars/'.$userId.'/delete/'.$calendar->getId(),
            '/calendars/'.$userId.'/revoke/'.$calendar->getId(),
            '/calendars/'.$userId.'/share/'.$calendar->getId().'?principalId=1&write=true',
        ] as $url) {
            $client->request('GET', $url);
            $this->assertResponseStatusCodeSame(405, 'GET '.$url.' must not be allowed');
        }

        $this->assertNotNull($calendarRepository->find($calendar->getId()));
    }

    public function testCalendarDeleteRejectsInvalidCsrfToken(): void
    {
        $user = new AdminUser('admin', 'test');

        $client = static::createClient();
        $client->loginUser($user);

        $userId = $this->getUserId($client, 'test_user');
        $calendarRepository = static::getContainer()->get(CalendarInstanceRepository::class);
        $calendar = $calendarRepository->findOneByDisplayName('default.calendar.title');

        $client->request('POST', '/calendars/'.$userId.'/delete/'.$calendar->getId(), ['_token' => 'not-the-token']);
        $this->assertResponseStatusCodeSame(403);

        $this->assertNotNull($calendarRepository->find($calendar->getId()));
    }

    private function loggedInClient(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = static::createClient();
        $client->loginUser(new AdminUser('admin', 'test'));

        return $client;
    }

    public function testCalendarActionsOnAnotherUsersCalendarAre404(): void
    {
        $client = $this->loggedInClient();

        $ownerId = $this->getUserId($client, 'test_user');
        $otherId = $this->getUserId($client, 'test_user2');
        $calendarRepository = static::getContainer()->get(CalendarInstanceRepository::class);
        $calendar = $calendarRepository->findOneByDisplayName('default.calendar.title');
        $sharee = static::getContainer()->get('doctrine.orm.entity_manager')->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.'test_user');

        $client->request('GET', '/calendars/'.$otherId.'/edit/'.$calendar->getId());
        $this->assertResponseStatusCodeSame(404);

        $client->request('GET', '/calendars/'.$otherId.'/shares/'.$calendar->getCalendar()->getId());
        $this->assertResponseStatusCodeSame(404);

        $this->postAdmin($client, '/calendars/'.$otherId.'/share/'.$calendar->getId(), ['principalId' => $sharee->getId(), 'write' => 'false']);
        $this->assertResponseStatusCodeSame(404);

        $this->postAdmin($client, '/calendars/'.$otherId.'/revoke/'.$calendar->getId());
        $this->assertResponseStatusCodeSame(404);

        $this->postAdmin($client, '/calendars/'.$otherId.'/delete/'.$calendar->getId());
        $this->assertResponseStatusCodeSame(404);

        $this->assertNotNull($calendarRepository->find($calendar->getId()), 'The owner\'s calendar must be untouched');
        $this->assertSame(0, $calendarRepository->count(['principalUri' => Principal::PREFIX.'test_user2', 'calendar' => $calendar->getCalendar()]));
        unset($ownerId);
    }

    public function testCalendarCannotBeSharedWithItsOwner(): void
    {
        $client = $this->loggedInClient();

        $userId = $this->getUserId($client, 'test_user');
        $calendarRepository = static::getContainer()->get(CalendarInstanceRepository::class);
        $calendar = $calendarRepository->findOneByDisplayName('default.calendar.title');
        $owner = static::getContainer()->get('doctrine.orm.entity_manager')->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.'test_user');

        $this->postAdmin($client, '/calendars/'.$userId.'/share/'.$calendar->getId(), ['principalId' => $owner->getId(), 'write' => 'true']);
        $this->assertResponseStatusCodeSame(400);

        $this->assertSame(1, $calendarRepository->count(['calendar' => $calendar->getCalendar()]));
    }

    public function testDeletingASharedInstanceOnlyRevokesTheShare(): void
    {
        $client = $this->loggedInClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        $ownerInstance = $em->getRepository(CalendarInstance::class)->findOneBy(['principalUri' => Principal::PREFIX.'test_user', 'uri' => 'default']);
        $calendar = $ownerInstance->getCalendar();

        $object = (new CalendarObject())->setCalendar($calendar)->setUri('event.ics')->setCalendarData('BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n')
            ->setEtag('e')->setSize(30)->setComponentType('VEVENT')->setUid('event-1')->setLastModified(time());
        $em->persist($object);
        $shared = (new CalendarInstance())->setCalendar($calendar)->setPrincipalUri(Principal::PREFIX.'test_user2')->setUri('shared-uuid')
            ->setAccess(SharingPlugin::ACCESS_READWRITE)->setDisplayName('Shared with me');
        $em->persist($shared);
        $em->flush();
        $sharedId = $shared->getId();
        $ownerInstanceId = $ownerInstance->getId();
        $calendarId = $calendar->getId();
        $em->clear();

        $shareeUserId = $this->getUserId($client, 'test_user2');
        $this->postAdmin($client, '/calendars/'.$shareeUserId.'/delete/'.$sharedId);
        $this->assertResponseRedirects('/calendars/'.$shareeUserId);

        $em->clear();
        $this->assertNull($em->getRepository(CalendarInstance::class)->find($sharedId), 'The share is gone');
        $this->assertNotNull($em->getRepository(CalendarInstance::class)->find($ownerInstanceId), 'The owner still has the calendar');
        $this->assertNotNull($em->getRepository(Calendar::class)->find($calendarId), 'The calendar row still exists');
        $this->assertSame(1, $em->getRepository(CalendarObject::class)->count(['calendar' => $calendarId]), 'The owner\'s events are intact');
    }

    public function testRevokingAnOwnerInstanceIsRejected(): void
    {
        $client = $this->loggedInClient();

        $userId = $this->getUserId($client, 'test_user');
        $calendarRepository = static::getContainer()->get(CalendarInstanceRepository::class);
        $calendar = $calendarRepository->findOneByDisplayName('default.calendar.title');

        $this->postAdmin($client, '/calendars/'.$userId.'/revoke/'.$calendar->getId());
        $this->assertResponseStatusCodeSame(400);

        $this->assertNotNull($calendarRepository->find($calendar->getId()));
    }

    public function testCalendarNewIgnoresASubmittedOwner(): void
    {
        $client = $this->loggedInClient();

        $userId = $this->getUserId($client, 'test_user');

        $crawler = $client->request('GET', '/calendars/'.$userId.'/new');
        $this->assertSelectorNotExists('input[name="calendar_instance[principalUri]"]');

        $form = $crawler->selectButton('calendar_instance_save')->form();
        $values = $form->getPhpValues();
        $values['calendar_instance']['uri'] = 'hijack';
        $values['calendar_instance']['displayName'] = 'Hijack';
        $values['calendar_instance']['principalUri'] = Principal::PREFIX.'test_user2';

        $client->request($form->getMethod(), $form->getUri(), $values);

        // Extra fields make the form invalid: re-rendered, nothing persisted
        $this->assertResponseIsSuccessful();
        $calendarRepository = static::getContainer()->get(CalendarInstanceRepository::class);
        $this->assertNull($calendarRepository->findOneBy(['uri' => 'hijack']));
    }
}
