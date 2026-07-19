<?php

namespace App\Tests\Functional;

use App\Security\AdminUser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

class AdminMutationSecurityTest extends WebTestCase
{
    public function testAdminMutationsRequirePostAndCsrfProtection(): void
    {
        $client = static::createClient();

        $mutations = [
            'addressbook_delete' => '/addressbooks/1/delete/1',
            'calendar_share_add' => '/calendars/1/share/1',
            'calendar_delete' => '/calendars/1/delete/1',
            'calendar_revoke' => '/calendars/1/revoke/1',
            'user_delete' => '/users/delete/1',
            'user_delegation_toggle' => '/users/delegation/1/off',
            'user_delegate_add' => '/users/delegates/1/add',
            'user_delegate_remove' => '/users/delegates/1/remove/1/1',
        ];

        $routes = static::getContainer()->get(RouterInterface::class)->getRouteCollection();
        foreach ($mutations as $routeName => $path) {
            $this->assertSame(['POST'], $routes->get($routeName)->getMethods(), $routeName.' is not POST-only');

            $client->loginUser(new AdminUser('admin', 'test'));
            $client->request('GET', '/dashboard');
            $this->assertResponseIsSuccessful();

            $client->request('POST', $path);
            $this->assertResponseRedirects('/login', 302, $path.' accepted a POST request without a CSRF token');
        }
    }
}
