<?php

namespace App\Services;

use Sabre\HTTP\ResponseInterface;
use Sabre\HTTP\Sapi;

/**
 * A SAPI that never sends anything.
 *
 * sabre/dav's default SAPI writes the status line and headers with header() and streams the
 * body to php://output. We want to hand a proper Symfony Response back to the kernel instead
 * (so that its events, like TERMINATE, keep working), so this SAPI leaves the response object
 * untouched and the DAVController reads it after the server ran.
 */
final class CapturingSapi extends Sapi
{
    public static function sendResponse(ResponseInterface $response): void
    {
        // Nothing to do: the response is read from $server->httpResponse by the controller
    }
}
