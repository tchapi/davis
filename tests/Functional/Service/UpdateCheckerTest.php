<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service;

use App\Services\UpdateChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The check talks to GitHub, which is the one thing the diagnostics page is otherwise careful
 * not to do, so what matters here is that it does so rarely and never takes the page down.
 */
class UpdateCheckerTest extends TestCase
{
    private static function release(string $tag, string $url = 'https://github.com/tchapi/davis/releases/tag/v99.0.0'): MockResponse
    {
        return new MockResponse(json_encode(['tag_name' => $tag, 'html_url' => $url], JSON_THROW_ON_ERROR), [
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    }

    public function testTheLatestReleaseIsReadFromGithub(): void
    {
        $http = new MockHttpClient(self::release('v99.0.0'));
        $checker = new UpdateChecker($http, new ArrayAdapter(), true, '5.6.3');

        $this->assertSame(['version' => '99.0.0', 'url' => 'https://github.com/tchapi/davis/releases/tag/v99.0.0'], $checker->latestRelease());
        $this->assertTrue($checker->isOutdated('99.0.0'));
        $this->assertFalse($checker->isOutdated('5.6.3'));
        $this->assertFalse($checker->isOutdated('v5.6.3'), 'The tag prefix must not make the same version look newer');
        $this->assertFalse($checker->isOutdated('5.6.2'));
    }

    public function testGithubIsAskedOnceThenTheAnswerIsCached(): void
    {
        $http = new MockHttpClient([self::release('v99.0.0'), self::release('v100.0.0')]);
        $checker = new UpdateChecker($http, new ArrayAdapter(), true, '5.6.3');

        $this->assertSame('99.0.0', $checker->latestRelease()['version'] ?? null);
        $this->assertSame('99.0.0', $checker->latestRelease()['version'] ?? null);
        $this->assertSame(1, $http->getRequestsCount());
    }

    public function testAFailedLookupIsNullAndNotRetriedOnEveryPageLoad(): void
    {
        $http = new MockHttpClient(new MockResponse('', ['http_code' => 503]));
        $checker = new UpdateChecker($http, new ArrayAdapter(), true, '5.6.3');

        $this->assertNull($checker->latestRelease());
        $this->assertNull($checker->latestRelease());
        $this->assertSame(1, $http->getRequestsCount());
    }

    public function testAPayloadThatIsNotAReleaseIsTreatedAsAFailure(): void
    {
        $checker = new UpdateChecker(new MockHttpClient(self::release('nightly')), new ArrayAdapter(), true, '5.6.3');

        $this->assertNull($checker->latestRelease());
    }

    /**
     * The link is shown to an administrator: it must stay on GitHub whatever the API returns.
     */
    public function testTheLinkFallsBackToTheReleasesPage(): void
    {
        $checker = new UpdateChecker(new MockHttpClient(self::release('v99.0.0', 'https://example.org/phishing')), new ArrayAdapter(), true, '5.6.3');

        $this->assertSame(UpdateChecker::RELEASES_URL, $checker->latestRelease()['url'] ?? null);
    }

    public function testTheRequestIdentifiesItselfAndDoesNotWaitLong(): void
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('GET', $method);
            $this->assertSame('https://api.github.com/repos/tchapi/davis/releases/latest', $url);
            $this->assertContains('User-Agent: Davis/5.6.3', $options['headers']);
            $this->assertLessThanOrEqual(5, $options['timeout']);

            return self::release('v5.6.3');
        });

        (new UpdateChecker($http, new ArrayAdapter(), true, '5.6.3'))->latestRelease();
        $this->assertSame(1, $http->getRequestsCount());
    }
}
