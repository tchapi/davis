<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;

class DavTest extends WebTestCase
{
    /**
     * The DAVController uses a sabre/dav that relies on REQUEST_URI and REQUEST_METHOD
     * which are not set by default by PHPUnit (or to the wrong values).
     * We thus force them here so that the request looks like a real one for PHPUnit.
     */
    public static function requestDavClient(string $method, string $path): AbstractBrowser
    {
        $_SERVER['REQUEST_URI'] = $path;
        $_SERVER['REQUEST_METHOD'] = $method;

        $client = static::createClient();
        $client->request($method, $path);

        return $client;
    }

    public function testUnauthorized(): void
    {
        $client = static::requestDavClient('GET', '/dav/');

        $this->assertResponseStatusCodeSame(401);
    }
}
