<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;

/**
 * State-changing admin actions are POST requests that must carry the CSRF token
 * published in the layout's <meta name="csrf-token"> tag.
 */
trait AdminPostTrait
{
    private function getAdminCsrfToken(KernelBrowser $client): string
    {
        $crawler = $client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();

        return $crawler->filter('meta[name="csrf-token"]')->attr('content');
    }

    /**
     * Fetches a valid CSRF token and POSTs it, together with $params, to $url.
     */
    private function postAdmin(KernelBrowser $client, string $url, array $params = []): Crawler
    {
        $token = $this->getAdminCsrfToken($client);

        return $client->request('POST', $url, array_merge(['_token' => $token], $params));
    }
}
