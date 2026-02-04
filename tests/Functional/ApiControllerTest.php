<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApiControllerTest extends WebTestCase
{
    private function getUserUsername($client): string {
        $client->request('GET', '/api/v1/users', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY']
        ]);
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        
        $data = json_decode($client->getResponse()->getContent(), true);
        return $data['data'][0]['username'];
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
    public function testUserList(): void {
        $client = static::createClient();
        $client->request('GET', '/api/v1/users', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY']
        ]);
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        
        // Check if user is present in db
        $this->assertArrayHasKey('id', $data['data'][0]);
        $this->assertArrayHasKey('uri', $data['data'][0]);
        $this->assertArrayHasKey('username', $data['data'][0]);
    }

    /*
     * Test the user details endpoint
     */
    public function testUserDetails(): void {
        // Create client once
        $client = static::createClient();
        
        // Get username from existing user lists
        $username = $this->getUserUsername($client);
        
        // Check user details endpoint
        $client->request('GET', '/api/v1/users/' . $username, [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY']
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        // Check if user details are correct
        $this->assertArrayHasKey('displayname', $data['data']);
        $this->assertArrayHasKey('email', $data['data']);
        $this->assertStringEqualsStringIgnoringLineEndings($username, $data['data']['username']);
    }

    /*
     * Test the user calendars list endpoint
     */
    public function testUserCalendarsList(): void {
        $client = static::createClient();
        $username = $this->getUserUsername($client);

        $client->request('GET', '/api/v1/calendars/' . $username, [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY']
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        // Check if calendar list is correct
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('success', $data['status']);
        $this->assertArrayHasKey('user_calendars', $data['data']);
        $this->assertArrayHasKey('shared_calendars', $data['data']);
        $this->assertArrayHasKey('subscriptions', $data['data']);
    }

    public function testUserCalendarDetails(): void {
        $client = static::createClient();
        $username = $this->getUserUsername($client);

        // Get calendar list to retrieve calendar ID
        $client->request('GET', '/api/v1/calendars/' . $username, [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY']
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        $calendar_id = $data['data']['user_calendars'][0]['id'];
        
        // Check calendar details endpoint
        $client->request('GET', '/api/v1/calendars/' . $username . '/' . $calendar_id, [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_DAVIS_API_TOKEN' => $_ENV['API_KEY']
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        // Check if calendar details are correct
        $this->assertArrayHasKey('id', $data['data']);
        $this->assertArrayHasKey('uri', $data['data']);
        $this->assertArrayHasKey('displayname', $data['data']);
        $this->assertArrayHasKey('description', $data['data']);
        
        $this->assertArrayHasKey('events', $data['data']);
        $this->assertIsArray($data['data']['events']);
        $this->assertArrayHasKey('notes', $data['data']);
        $this->assertIsArray($data['data']['notes']);
        $this->assertArrayHasKey('tasks', $data['data']);
        $this->assertIsArray($data['data']['tasks']);
    }

    // TODO: TestCreateUser
    // TODO: TestShareCalendarToNewUser
    // TODO: TestShareCalendarList
    // TODO: TestUnshareCalendarToNewUser
    // TODO: TestCreateCalendarForUser
    // TODO: TestRemoveCalendarForUser
}
