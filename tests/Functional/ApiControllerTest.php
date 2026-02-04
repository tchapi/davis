<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApiControllerTest extends WebTestCase
{
    /*
     * Helper function to get an existing username from the user list
     *
     * @param mixed $client
     *
     * @return string Username
     */
    private function getUserUsername($client): string
    {
        $client->request('GET', '/api/v1/users', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertStringContainsString('test_user', $data['data'][0]['username']);

        return $data['data'][0]['username'];
    }

    /*
     * Helper function to get an existing calendar ID from the user calendar list
     *
     * @param mixed  $client
     * @param string $username
     * @param bool   $default  Whether to get the default calendar (true) or the second calendar (false)
     *
     * @return int Calendar ID
     */
    private function getCalendarId($client, string $username, bool $default = true): int
    {
        $client->request('GET', '/api/v1/calendars/'.$username, [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY'],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);

        if ($default) {
            $this->assertMatchesRegularExpression('/^\d+$/', $data['data']['user_calendars'][0]['id']);

            return $data['data']['user_calendars'][0]['id'];
        } else {
            $this->assertMatchesRegularExpression('/^\d+$/', $data['data']['user_calendars'][1]['id']);

            return $data['data']['user_calendars'][1]['id'];
        }
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

        // Check if user is present in db
        $this->assertArrayHasKey('id', $data['data'][0]);
        $this->assertArrayHasKey('uri', $data['data'][0]);
        $this->assertStringContainsString('principals/test_user', $data['data'][0]['uri']);
        $this->assertArrayHasKey('username', $data['data'][0]);
        $this->assertStringContainsString('test_user', $data['data'][0]['username']);
    }

    /*
     * Test the user details endpoint
     */
    public function testUserDetails(): void
    {
        // Create client once
        $client = static::createClient();

        // Get username from existing user lists
        $username = $this->getUserUsername($client);

        // Check user details endpoint
        $client->request('GET', '/api/v1/users/'.$username, [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY'],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);

        // Check if user details are correct
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
        $username = $this->getUserUsername($client);

        $client->request('GET', '/api/v1/calendars/'.$username, [], [], [
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
        $username = $this->getUserUsername($client);

        // Get calendar list to retrieve calendar ID
        $client->request('GET', '/api/v1/calendars/'.$username, [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY'],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $calendar_id = $data['data']['user_calendars'][0]['id'];

        // Check calendar details endpoint
        $client->request('GET', '/api/v1/calendars/'.$username.'/'.$calendar_id, [], [], [
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
        $username = $this->getUserUsername($client);

        // Create user API request with JSON body
        $payload = [
            'uri' => 'api_calendar',
            'name' => 'api.calendar.title',
            'description' => 'api.calendar.description',
            'events_support' => true,
            'tasks_support' => true,
            'notes_support' => false,
        ];

        $client->request('POST', '/api/v1/calendars/'.$username.'/create', [], [], [
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
        $client->request('GET', '/api/v1/calendars/'.$username.'/'.$calendarId, [], [], [
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
        $username = $this->getUserUsername($client);
        $calendar_id = $this->getCalendarId($client, $username, true);

        // Edit user default calendar
        $payload = [
            'name' => 'api.calendar.edited.title',
            'description' => 'api.calendar.edited.description',
            'events_support' => true,
            'tasks_support' => true,
            'notes_support' => true,
        ];
        $client->request('POST', '/api/v1/calendars/'.$username.'/'.$calendar_id.'/edit', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY'],
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('success', $data['status']);

        // Check if edits were applied
        $client->request('GET', '/api/v1/calendars/'.$username.'/'.$calendar_id, [], [], [
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

    // TODO: TestShareCalendarToUser
    // TODO: TestShareCalendarList
    // TODO: TestUnshareCalendarToUser
}
