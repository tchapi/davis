<?php

namespace App\Tests\Functional;

use Symfony\Component\BrowserKit\AbstractBrowser;

/**
 * The DAVController uses a sabre/dav that relies on REQUEST_URI and REQUEST_METHOD
 * which are not set by default by PHPUnit (or to the wrong values).
 * We thus force them here so that the request looks like a real one for PHPUnit.
 *
 * The Authorization header is read by sabre/dav from $_SERVER too, so we set (or unset)
 * it the same way.
 */
trait DavRequestTrait
{
    protected static function requestDav(AbstractBrowser $client, string $method, string $path, ?string $basicAuthUserPass = null): void
    {
        $_SERVER['REQUEST_URI'] = $path;
        $_SERVER['REQUEST_METHOD'] = $method;

        if (null !== $basicAuthUserPass) {
            $_SERVER['HTTP_AUTHORIZATION'] = 'Basic '.base64_encode($basicAuthUserPass);
        } else {
            unset($_SERVER['HTTP_AUTHORIZATION']);
        }

        $client->request($method, $path);
    }
}
