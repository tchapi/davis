<?php

namespace App\Tests\Functional;

use App\Entity\Calendar;
use App\Entity\CalendarInstance;
use App\Entity\CalendarObject;
use App\Entity\CalendarSubscription;
use App\Entity\Principal;
use App\Entity\SchedulingObject;
use Sabre\DAV\Sharing\Plugin as SharingPlugin;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApiControllerTest extends WebTestCase
{
    /*
     * Helper function to get an existing user ID from the user list
     *
     * @param int  $index   Index of the user in the list (0 - first user, 1 - second user)
     * @param mixed $client
     *
     * @return int User ID
     */
    private function getUserId($client, int $index): int
    {
        $client->request('GET', '/api/v1/users', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertStringContainsString('test_user', $data['data'][$index]['username']);

        return $data['data'][$index]['user_id'];
    }

    /*
     * Helper function to get an existing username from the user list
     *
     * @param int  $index   Index of the user in the list (0 - first user, 1 - second user)
     * @param mixed $client
     *
     * @return string Username
     */
    private function getUserUsername($client, int $index): string
    {
        $client->request('GET', '/api/v1/users', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertStringContainsString('test_user', $data['data'][$index]['username']);

        return $data['data'][$index]['username'];
    }

    /*
     * Helper function to get an existing calendar ID from the user calendar list
     *
     * @param mixed $client
     * @param int   $userId
     * @param bool  $default  Whether to get the default calendar (true) or the second calendar (false)
     *
     * @return int Calendar ID
     */
    private function getCalendarId($client, int $userId, bool $default = true): int
    {
        $client->request('GET', '/api/v1/calendars/'.$userId, [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY'],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);

        if ($default) {
            $this->assertMatchesRegularExpression('/^\d+$/', $data['data']['user_calendars'][0]['id']);

            return $data['data']['user_calendars'][0]['id'];
        }
        $this->assertMatchesRegularExpression('/^\d+$/', $data['data']['user_calendars'][1]['id']);

        return $data['data']['user_calendars'][1]['id'];
    }

    /*
     * Test the health endpoint
     */
    public function testHealth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/health');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('OK', $data['status']);
    }

    /*
     * Test the API endpoint with invalid token
     */
    public function testApiInvalidToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/users', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => 'invalid_token',
        ]);
        $this->assertResponseStatusCodeSame(401);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('error', $data['status']);
        $this->assertEquals('Invalid X-Davis-API-Token header', $data['message']);
    }

    /*
     * Test the API endpoint with missing token
     */
    public function testApiMissingToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/users', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $this->assertResponseStatusCodeSame(401);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('error', $data['status']);
        $this->assertEquals('Missing X-Davis-API-Token header', $data['message']);
    }

    /*
     * Test the user list endpoint
     */
    public function testUserList(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/users', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);

        // Check if user1 is present in db
        $this->assertArrayHasKey('user_id', $data['data'][0]);
        $this->assertArrayHasKey('principal_id', $data['data'][0]);
        $this->assertArrayHasKey('uri', $data['data'][0]);
        $this->assertStringContainsString('principals/test_user', $data['data'][0]['uri']);
        $this->assertArrayHasKey('username', $data['data'][0]);
        $this->assertStringContainsString('test_user', $data['data'][0]['username']);

        // Check if user2 is present in db
        $this->assertArrayHasKey('user_id', $data['data'][1]);
        $this->assertArrayHasKey('principal_id', $data['data'][1]);
        $this->assertArrayHasKey('uri', $data['data'][1]);
        $this->assertStringContainsString('principals/test_user2', $data['data'][1]['uri']);
        $this->assertArrayHasKey('username', $data['data'][1]);
        $this->assertStringContainsString('test_user2', $data['data'][1]['username']);
    }

    /*
     * Test the user details endpoint
     */
    public function testUserDetails(): void
    {
        // Create client once
        $client = static::createClient();

        // Get userId and username from existing user lists
        $userId = $this->getUserId($client, 0);
        $username = $this->getUserUsername($client, 0);

        // Check user details endpoint
        $client->request('GET', '/api/v1/users/'.$userId, [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY'],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);

        // Check if user details are correct
        $this->assertArrayHasKey('user_id', $data['data']);
        $this->assertEquals($userId, $data['data']['user_id']);
        $this->assertArrayHasKey('displayname', $data['data']);
        $this->assertStringContainsString('Test User', $data['data']['displayname']);
        $this->assertArrayHasKey('email', $data['data']);
        $this->assertStringContainsString('test@test.com', $data['data']['email']);
        $this->assertStringEqualsStringIgnoringLineEndings($username, $data['data']['username']);
    }

    /*
     * Test the user calendars list endpoint
     */
    public function testUserCalendarsList(): void
    {
        $client = static::createClient();
        $userId = $this->getUserId($client, 0);

        $client->request('GET', '/api/v1/calendars/'.$userId, [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY'],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);

        // Check if calendar list is correct
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('success', $data['status']);
        $this->assertArrayHasKey('user_calendars', $data['data']);
        $this->assertStringContainsString('default', $data['data']['user_calendars'][0]['uri']);
        $this->assertStringContainsString('default.calendar.title', $data['data']['user_calendars'][0]['displayname']);
        $this->assertArrayHasKey('shared_calendars', $data['data']);
        $this->assertArrayHasKey('subscriptions', $data['data']);
    }

    /*
     * Test the user calendar details endpoint
     */
    public function testUserCalendarDetails(): void
    {
        $client = static::createClient();
        $userId = $this->getUserId($client, 0);

        // Get calendar list to retrieve calendar ID
        $client->request('GET', '/api/v1/calendars/'.$userId, [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY'],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $calendar_id = $data['data']['user_calendars'][0]['id'];

        // Check calendar details endpoint
        $client->request('GET', '/api/v1/calendars/'.$userId.'/'.$calendar_id, [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY'],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);

        // Check if calendar details are correct
        $this->assertArrayHasKey('id', $data['data']);
        $this->assertArrayHasKey('uri', $data['data']);
        $this->assertStringContainsString('default', $data['data']['uri']);
        $this->assertArrayHasKey('displayname', $data['data']);
        $this->assertStringContainsString('default.calendar.title', $data['data']['displayname']);

        $this->assertArrayHasKey('description', $data['data']);
        $this->assertStringContainsString('default.calendar.description', $data['data']['description']);

        $this->assertArrayHasKey('events', $data['data']);
        $this->assertArrayHasKey('notes', $data['data']);
        $this->assertArrayHasKey('tasks', $data['data']);
    }

    /*
     * Test creating a new user calendar
     */
    public function testCreateUserCalendar(): void
    {
        $client = static::createClient();
        $userId = $this->getUserId($client, 0);

        // Create calendar API request with JSON body
        $payload = [
            'uri' => 'api_calendar',
            'name' => 'api.calendar.title',
            'description' => 'api.calendar.description',
            'events_support' => true,
            'tasks_support' => true,
            'notes_support' => false,
        ];

        $client->request('POST', '/api/v1/calendars/'.$userId.'/create', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY'],
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('success', $data['status']);
        $this->assertArrayHasKey('data', $data);
        $this->assertMatchesRegularExpression('/^\d+$/', $data['data']['calendar_id']);
        $this->assertStringContainsString('api_calendar', $data['data']['calendar_uri']);

        // Check if calendar is created
        $calendarId = $data['data']['calendar_id'];
        $client->request('GET', '/api/v1/calendars/'.$userId.'/'.$calendarId, [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY'],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('events', $data['data']);
        $this->assertTrue($data['data']['events']['enabled']);
        $this->assertArrayHasKey('tasks', $data['data']);
        $this->assertTrue($data['data']['tasks']['enabled']);
        $this->assertArrayHasKey('notes', $data['data']);
        $this->assertFalse($data['data']['notes']['enabled']);
    }

    /*
     * Test editing a user calendar
     */
    public function testEditUserCalendar(): void
    {
        $client = static::createClient();
        $userId = $this->getUserId($client, 0);
        $calendarId = $this->getCalendarId($client, $userId, true);

        // Edit user default calendar
        $payload = [
            'name' => 'api.calendar.edited.title',
            'description' => 'api.calendar.edited.description',
            'events_support' => true,
            'tasks_support' => true,
            'notes_support' => true,
        ];
        $client->request('PATCH', '/api/v1/calendars/'.$userId.'/'.$calendarId, [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY'],
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('success', $data['status']);

        // Check if edits were applied
        $client->request('GET', '/api/v1/calendars/'.$userId.'/'.$calendarId, [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY'],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('data', $data);
        $this->assertStringContainsString($payload['name'], $data['data']['displayname']);
        $this->assertStringContainsString($payload['description'], $data['data']['description']);

        $this->assertArrayHasKey('events', $data['data']);
        $this->assertTrue($data['data']['events']['enabled']);
        $this->assertArrayHasKey('tasks', $data['data']);
        $this->assertTrue($data['data']['tasks']['enabled']);
        $this->assertArrayHasKey('notes', $data['data']);
        $this->assertTrue($data['data']['notes']['enabled']);
    }

    /*
     * Test getting shares for a user calendar (should be empty initially)
     */
    public function testGetUserCalendarSharesEmpty(): void
    {
        $client = static::createClient();
        $userId = $this->getUserId($client, 0);
        $calendarId = $this->getCalendarId($client, $userId, true);

        // Get shares for user default calendar
        $client->request('GET', '/api/v1/calendars/'.$userId.'/shares/'.$calendarId, [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY'],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('success', $data['status']);
        $this->assertArrayHasKey('data', $data);
        $this->assertEmpty($data['data']);
    }

    /*
     * Test sharing user calendar to another user
     */
    public function testShareUserCalendar(): void
    {
        $client = static::createClient();
        $userId = $this->getUserId($client, 0);
        $shareeUsername = $this->getUserUsername($client, 1);
        $calendarId = $this->getCalendarId($client, $userId, true);

        // Share user default calendar to test_user2
        $payload = [
            'username' => $shareeUsername,
            'write_access' => false,
        ];
        $client->request('POST', '/api/v1/calendars/'.$userId.'/share/'.$calendarId.'/add', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY'],
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('success', $data['status']);

        // Check if share was applied
        $client->request('GET', '/api/v1/calendars/'.$userId.'/shares/'.$calendarId, [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY'],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('success', $data['status']);
        $this->assertArrayHasKey('data', $data);
        $this->assertStringContainsString($shareeUsername, $data['data'][0]['username']);
        $this->assertArrayHasKey('user_id', $data['data'][0]);
        $this->assertIsNumeric($data['data'][0]['user_id']);
        $this->assertFalse($data['data'][0]['write_access']);
    }

    /*
     * Test removing shared access to user calendar
     */
    public function testUnshareUserCalendar(): void
    {
        $client = static::createClient();
        $userId = $this->getUserId($client, 0);
        $shareeUsername = $this->getUserUsername($client, 1);
        $calendarId = $this->getCalendarId($client, $userId, true);

        // Unshare user default calendar from test_user2
        $payload = [
            'username' => $shareeUsername,
        ];
        $client->request('POST', '/api/v1/calendars/'.$userId.'/share/'.$calendarId.'/remove', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY'],
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('success', $data['status']);

        // Check if unshare was applied
        $client->request('GET', '/api/v1/calendars/'.$userId.'/shares/'.$calendarId, [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY'],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('success', $data['status']);
        $this->assertArrayHasKey('data', $data);
        $this->assertEmpty($data['data']);
    }

    /*
     * Test creating a calendar with no components enabled should return validation error
     */
    public function testCreateUserCalendarNoComponents(): void
    {
        $client = static::createClient();
        $userId = $this->getUserId($client, 0);

        // Create calendar API request with no components enabled
        $payload = [
            'uri' => 'no_components_calendar',
            'name' => 'no.components.calendar',
            'description' => 'no.components.description',
            'events_support' => false,
            'tasks_support' => false,
            'notes_support' => false,
        ];

        $client->request('POST', '/api/v1/calendars/'.$userId.'/create', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY'],
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $this->assertResponseStatusCodeSame(400);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('error', $data['status']);
        $this->assertStringContainsString('At least one calendar component must be enabled', $data['message']);
    }

    /*
     * Test editing a calendar with no components enabled should return validation error
     */
    public function testEditUserCalendarNoComponents(): void
    {
        $client = static::createClient();
        $userId = $this->getUserId($client, 0);
        $calendarId = $this->getCalendarId($client, $userId, true);

        // Edit calendar API request with no components enabled
        $payload = [
            'name' => 'edited.calendar.title',
            'description' => 'edited.calendar.description',
            'events_support' => false,
            'tasks_support' => false,
            'notes_support' => false,
        ];

        $client->request('PUT', '/api/v1/calendars/'.$userId.'/'.$calendarId, [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY'],
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $this->assertResponseStatusCodeSame(400);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('error', $data['status']);
        $this->assertStringContainsString('At least one calendar component must be enabled', $data['message']);
    }

    /*
    * Test deleting a user calendar
    */
    public function testDeleteUserCalendar(): void
    {
        $client = static::createClient();
        $userId = $this->getUserId($client, 0);
        $calendarId = $this->getCalendarId($client, $userId, true);

        // Delete the calendar
        $client->request('DELETE', '/api/v1/calendars/'.$userId.'/'.$calendarId, [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY'],
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('success', $data['status']);

        // Check if calendar is deleted
        $client->request('GET', '/api/v1/calendars/'.$userId.'/'.$calendarId, [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY'],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('success', $data['status']);
        $this->assertEmpty($data['data']);
    }

    private function apiHeaders(): array
    {
        return [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY'],
            'CONTENT_TYPE' => 'application/json',
        ];
    }

    /**
     * Shares test_user's default calendar with test_user2 (directly in the database) and
     * adds one event to it. Returns [ownerInstanceId, sharedInstanceId, calendarId].
     */
    private function seedSharedCalendar(): array
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        $ownerInstance = $em->getRepository(CalendarInstance::class)->findOneBy(['principalUri' => Principal::PREFIX.'test_user', 'uri' => 'default']);
        $calendar = $ownerInstance->getCalendar();

        $object = (new CalendarObject())->setCalendar($calendar)->setUri('event.ics')->setCalendarData("BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n")
            ->setEtag('e')->setSize(30)->setComponentType('VEVENT')->setUid('event-1')->setLastModified(time());
        $em->persist($object);
        $shared = (new CalendarInstance())->setCalendar($calendar)->setPrincipalUri(Principal::PREFIX.'test_user2')->setUri('shared-uuid')
            ->setAccess(SharingPlugin::ACCESS_READWRITE)->setDisplayName('Shared with me');
        $em->persist($shared);
        $em->flush();

        $ids = [$ownerInstance->getId(), $shared->getId(), $calendar->getId()];
        $em->clear();

        return $ids;
    }

    public function testDeleteUserCalendarKeepsSubscriptionsAndUnrelatedSchedulingObjects(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        $subscription = (new CalendarSubscription())->setPrincipalUri(Principal::PREFIX.'test_user')->setUri('holidays')
            ->setSource('https://example.org/holidays.ics')->setDisplayName('Holidays')->setCalendarOrder(0)->setLastModified(time());
        $em->persist($subscription);
        $inboxItem = (new SchedulingObject())->setPrincipalUri(Principal::PREFIX.'test_user')->setUri('invite.ics')
            ->setCalendarData("BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n")->setEtag('e')->setSize(30)->setLastModified(time());
        $em->persist($inboxItem);
        $em->flush();
        $em->clear();

        $userId = $this->getUserId($client, 0);
        $calendarId = $this->getCalendarId($client, $userId, true);

        $client->request('DELETE', '/api/v1/calendars/'.$userId.'/'.$calendarId, [], [], $this->apiHeaders());
        $this->assertResponseIsSuccessful();

        $em->clear();
        $this->assertSame(1, $em->getRepository(CalendarSubscription::class)->count(['principalUri' => Principal::PREFIX.'test_user']), 'Subscriptions must survive a calendar deletion');
        $this->assertSame(1, $em->getRepository(SchedulingObject::class)->count(['principalUri' => Principal::PREFIX.'test_user']), 'Unrelated inbox items must survive a calendar deletion');
    }

    public function testDeleteSharedCalendarInstanceOnlyRemovesTheShare(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        [$ownerInstanceId, $sharedId, $calendarId] = $this->seedSharedCalendar();

        $shareeUserId = $this->getUserId($client, 1);

        $client->request('DELETE', '/api/v1/calendars/'.$shareeUserId.'/'.$sharedId, [], [], $this->apiHeaders());
        $this->assertResponseIsSuccessful();
        $this->assertEquals('success', json_decode($client->getResponse()->getContent(), true)['status']);

        $em->clear();
        $this->assertNull($em->getRepository(CalendarInstance::class)->find($sharedId), 'The share is gone');
        $this->assertNotNull($em->getRepository(CalendarInstance::class)->find($ownerInstanceId), 'The owner still has the calendar');
        $this->assertNotNull($em->getRepository(Calendar::class)->find($calendarId), 'The calendar row still exists');
        $this->assertSame(1, $em->getRepository(CalendarObject::class)->count(['calendar' => $calendarId]), 'The owner\'s events are intact');
    }

    public function testSharedCalendarInstanceCannotBeEditedOrReSharedBySharee(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        [, $sharedId, $calendarId] = $this->seedSharedCalendar();

        $shareeUserId = $this->getUserId($client, 1);

        $client->request('PUT', '/api/v1/calendars/'.$shareeUserId.'/'.$sharedId, [], [], $this->apiHeaders(), json_encode(['name' => 'Hijacked', 'events_support' => false, 'tasks_support' => true]));
        $this->assertResponseStatusCodeSame(400);

        $client->request('GET', '/api/v1/calendars/'.$shareeUserId.'/shares/'.$sharedId, [], [], $this->apiHeaders());
        $this->assertResponseStatusCodeSame(400);

        $client->request('POST', '/api/v1/calendars/'.$shareeUserId.'/share/'.$sharedId.'/add', [], [], $this->apiHeaders(), json_encode(['username' => 'test_user2', 'write_access' => true]));
        $this->assertResponseStatusCodeSame(400);

        $client->request('POST', '/api/v1/calendars/'.$shareeUserId.'/share/'.$sharedId.'/remove', [], [], $this->apiHeaders(), json_encode(['username' => 'test_user2']));
        $this->assertResponseStatusCodeSame(400);

        $em->clear();
        $this->assertSame('VEVENT', $em->getRepository(Calendar::class)->find($calendarId)->getComponents(), 'Components of the shared calendar are unchanged');
        $this->assertNotNull($em->getRepository(CalendarInstance::class)->find($sharedId), 'The share still exists');
    }

    public function testShareCalendarWithItsOwnerIsRejected(): void
    {
        $client = static::createClient();
        $userId = $this->getUserId($client, 0);
        $calendarId = $this->getCalendarId($client, $userId, true);

        $client->request('POST', '/api/v1/calendars/'.$userId.'/share/'.$calendarId.'/add', [], [], $this->apiHeaders(), json_encode(['username' => 'test_user', 'write_access' => true]));
        $this->assertResponseStatusCodeSame(400);

        $client->request('GET', '/api/v1/calendars/'.$userId.'/shares/'.$calendarId, [], [], $this->apiHeaders());
        $this->assertResponseIsSuccessful();
        $this->assertSame([], json_decode($client->getResponse()->getContent(), true)['data']);
    }

    public function testSharesListToleratesAPrincipalWithoutUser(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        // A principal that has no matching row in `users` (e.g. a deleted user)
        $ghost = (new Principal())->setUri(Principal::PREFIX.'ghost')->setEmail('ghost@test.com')->setDisplayName('Ghost')->setIsAdmin(false);
        $em->persist($ghost);
        $ownerInstance = $em->getRepository(CalendarInstance::class)->findOneBy(['principalUri' => Principal::PREFIX.'test_user', 'uri' => 'default']);
        $shared = (new CalendarInstance())->setCalendar($ownerInstance->getCalendar())->setPrincipalUri(Principal::PREFIX.'ghost')->setUri('ghost-uuid')
            ->setAccess(SharingPlugin::ACCESS_READ)->setDisplayName('x');
        $em->persist($shared);
        $em->flush();
        $ownerInstanceId = $ownerInstance->getId();
        $em->clear();

        $userId = $this->getUserId($client, 0);
        $client->request('GET', '/api/v1/calendars/'.$userId.'/shares/'.$ownerInstanceId, [], [], $this->apiHeaders());
        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $data['data']);
        $this->assertSame('ghost', $data['data'][0]['username']);
        $this->assertNull($data['data'][0]['user_id']);
    }
}
