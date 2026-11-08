<?php

namespace App\Services;

use App\Version;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Looks up the latest Davis release on GitHub so the diagnostics page can say when an install is
 * behind.
 *
 * Opt-in (`UPDATE_CHECK_ENABLED`), because self-hosted software should not phone home unless
 * asked to. When enabled, GitHub is asked at most once a day, and a failed lookup is remembered
 * for an hour so an unreachable network does not slow every dashboard load.
 */
final class UpdateChecker
{
    public const RELEASES_URL = 'https://github.com/tchapi/davis/releases';

    private const LATEST_RELEASE_URL = 'https://api.github.com/repos/tchapi/davis/releases/latest';
    private const CACHE_KEY = 'davis.update_check.latest_release';
    private const TTL = 86400;
    private const TTL_AFTER_FAILURE = 3600;
    private const TIMEOUT = 3.0;

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        private bool $enabled = false,
        private string $currentVersion = Version::VERSION,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function currentVersion(): string
    {
        return $this->currentVersion;
    }

    /**
     * The latest published release, or null when it could not be looked up (offline, rate
     * limited, unexpected payload). Both outcomes are cached, with different lifetimes.
     *
     * @return array{version: string, url: string}|null
     */
    public function latestRelease(): ?array
    {
        $cached = $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
            try {
                $release = $this->fetchLatestRelease();
            } catch (\Throwable $e) {
                $item->expiresAfter(self::TTL_AFTER_FAILURE);

                return ['error' => $e->getMessage()];
            }

            $item->expiresAfter(self::TTL);

            return $release;
        });

        return isset($cached['version']) ? $cached : null;
    }

    /**
     * Whether the running version is behind the given one. Tags carry a `v` prefix, the
     * constant does not.
     */
    public function isOutdated(string $latestVersion): bool
    {
        return version_compare(self::normalize($this->currentVersion), self::normalize($latestVersion), '<');
    }

    public static function normalize(string $version): string
    {
        return ltrim(trim($version), 'vV');
    }

    /**
     * @return array{version: string, url: string}
     */
    private function fetchLatestRelease(): array
    {
        $response = $this->httpClient->request('GET', self::LATEST_RELEASE_URL, [
            'headers' => [
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'Davis/'.$this->currentVersion,
            ],
            'timeout' => self::TIMEOUT,
            'max_duration' => 2 * self::TIMEOUT,
        ]);

        $release = $response->toArray();
        $version = self::normalize((string) ($release['tag_name'] ?? ''));

        if (!preg_match('/^\d+(\.\d+)*/', $version)) {
            throw new \UnexpectedValueException(sprintf('Unexpected tag name "%s"', $release['tag_name'] ?? ''));
        }

        $url = (string) ($release['html_url'] ?? '');

        return [
            'version' => $version,
            'url' => str_starts_with($url, 'https://github.com/') ? $url : self::RELEASES_URL,
        ];
    }
}
