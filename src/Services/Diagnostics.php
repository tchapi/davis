<?php

namespace App\Services;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\Migrations\DependencyFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Answers "why is Davis not doing the thing I configured".
 *
 * Every check is passive: the page has to stay usable precisely when something is down, so it
 * opens no socket and runs no migration. `davis:mail:test` is the active counterpart for mail.
 */
final class Diagnostics
{
    public const DANGER = 'danger';
    public const WARNING = 'warning';
    public const OK = 'ok';
    public const INFO = 'info';

    private const RANK = [self::DANGER => 0, self::WARNING => 1, self::OK => 2, self::INFO => 3];

    public function __construct(
        private Connection $connection,
        private DependencyFactory $migrations,
        private UrlGeneratorInterface $router,
        private string $environment,
        private bool $debug,
        private string $logFilePath,
        private string $timezoneParameter,
        private string $authMethod,
        private string $authRealm,
        private bool $calDAVEnabled,
        private bool $cardDAVEnabled,
        private bool $webDAVEnabled,
        private bool $webdavPublicDirWritable,
        private ?string $inviteAddress,
        private ?string $mailerDsn,
    ) {
    }

    /**
     * @return array<int, array{title: string, checks: array<int, array<string, string|null>>}>
     */
    public function buckets(): array
    {
        $buckets = [
            'diagnostics.bucket.runtime' => [
                $this->versions(),
                $this->environment(),
                $this->timezone(),
                $this->logFile(),
            ],
            'diagnostics.bucket.database' => [
                $this->database(),
                $this->pendingMigrations(),
                $this->brokenSubscriptions(),
            ],
            'diagnostics.bucket.authentication' => [
                $this->auth(),
                $this->authExtension(),
            ],
            'diagnostics.bucket.scheduling' => [
                $this->inviteAddress(),
                $this->mailer(),
                $this->accountsWithoutEmail(),
            ],
            'diagnostics.bucket.endpoints' => array_values(array_filter([
                $this->davEndpoint(),
                $this->protocols(),
                $this->webdavPublicDir(),
            ])),
        ];

        $result = [];
        foreach ($buckets as $title => $checks) {
            usort($checks, fn (array $a, array $b) => self::RANK[$a['severity']] <=> self::RANK[$b['severity']]);
            $result[] = ['title' => $title, 'checks' => $checks];
        }

        return $result;
    }

    /**
     * How many checks an administrator should look at, for the pointer on the dashboard.
     */
    public function attentionCount(): int
    {
        $count = 0;
        foreach ($this->buckets() as $bucket) {
            foreach ($bucket['checks'] as $check) {
                if (self::DANGER === $check['severity'] || self::WARNING === $check['severity']) {
                    ++$count;
                }
            }
        }

        return $count;
    }

    /**
     * @param string|null $fix     translation key describing what to do about it
     * @param string|null $command a literal command or query, shown as-is
     */
    private function check(string $severity, string $title, string $value, ?string $fix = null, ?string $command = null, ?string $link = null): array
    {
        return compact('severity', 'title', 'value', 'fix', 'command', 'link');
    }

    private function versions(): array
    {
        return $this->check(self::INFO, 'diagnostics.versions', sprintf('Davis %s · sabre/dav %s · PHP %s', \App\Version::VERSION, \Sabre\DAV\Version::VERSION, PHP_VERSION));
    }

    private function environment(): array
    {
        $value = $this->environment.($this->debug ? ' (debug)' : '');

        if ('prod' !== $this->environment || $this->debug) {
            return $this->check(self::WARNING, 'diagnostics.environment', $value, 'diagnostics.environment.fix', 'APP_ENV=prod');
        }

        return $this->check(self::OK, 'diagnostics.environment', $value);
    }

    private function timezone(): array
    {
        $actual = date_default_timezone_get();

        if ('' !== $this->timezoneParameter && !\in_array($this->timezoneParameter, \DateTimeZone::listIdentifiers(), true)) {
            return $this->check(self::DANGER, 'diagnostics.timezone', $this->timezoneParameter, 'diagnostics.timezone.fix.bad', 'TIMEZONE=Europe/Paris');
        }

        if ('' === $this->timezoneParameter) {
            return $this->check(self::WARNING, 'diagnostics.timezone', $actual, 'diagnostics.timezone.fix.unset', 'TIMEZONE=Europe/Paris');
        }

        return $this->check(self::OK, 'diagnostics.timezone', $actual);
    }

    /**
     * `LOG_FILE_PATH` resolves through container parameters, so the configured value is not where
     * the file lands, and a directory the runtime user cannot write to means nothing is recorded.
     */
    private function logFile(): array
    {
        $directory = \dirname($this->logFilePath);

        if (!is_dir($directory) || !is_writable($directory)) {
            return $this->check(self::DANGER, 'diagnostics.log_file', $this->logFilePath, 'diagnostics.log_file.fix', 'chown -R www-data var/');
        }

        return $this->check(self::OK, 'diagnostics.log_file', $this->logFilePath);
    }

    /**
     * SQLite enforces neither foreign keys nor column lengths, which changes how several failures
     * behave, so it is worth stating which engine an install really runs on.
     */
    private function database(): array
    {
        $driver = $this->connection->getParams()['driver'] ?? 'unknown';

        try {
            // Platforms are identified by class: getName() is deprecated in doctrine/dbal.
            $isSqlite = $this->connection->getDatabasePlatform() instanceof SqlitePlatform;
            $version = $isSqlite
                ? (string) $this->connection->fetchOne('SELECT sqlite_version()')
                : (string) $this->connection->fetchOne('SELECT VERSION()');
        } catch (\Throwable $e) {
            return $this->check(self::DANGER, 'diagnostics.database', $driver, 'diagnostics.database.fix.unreachable', $e->getMessage());
        }

        if ($isSqlite) {
            return $this->check(self::WARNING, 'diagnostics.database', $driver.' '.$version, 'diagnostics.database.fix.sqlite');
        }

        return $this->check(self::OK, 'diagnostics.database', $driver.' '.$version);
    }

    /**
     * An install running without its migrations looks like a set of unrelated 500s: a PROPPATCH
     * that always fails, an address book that cannot be created, contacts that stop syncing.
     */
    private function pendingMigrations(): array
    {
        try {
            $executed = $this->migrations->getMetadataStorage()->getExecutedMigrations();
            $pending = 0;
            foreach ($this->migrations->getMigrationRepository()->getMigrations()->getItems() as $migration) {
                if (!$executed->hasMigration($migration->getVersion())) {
                    ++$pending;
                }
            }
        } catch (\Throwable $e) {
            return $this->check(self::DANGER, 'diagnostics.migrations', $e->getMessage(), 'diagnostics.migrations.fix', 'php bin/console doctrine:migrations:migrate');
        }

        if ($pending > 0) {
            return $this->check(self::DANGER, 'diagnostics.migrations', (string) $pending, 'diagnostics.migrations.fix', 'php bin/console doctrine:migrations:migrate');
        }

        return $this->check(self::OK, 'diagnostics.migrations', '0');
    }

    /**
     * A row with no source makes sabre throw while listing the calendar home, taking the whole
     * account's calendar collection down with it.
     */
    private function brokenSubscriptions(): array
    {
        try {
            $count = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM calendarsubscriptions WHERE source IS NULL OR source = ''");
        } catch (\Throwable $e) {
            return $this->check(self::INFO, 'diagnostics.broken_subscriptions', $e->getMessage());
        }

        if ($count > 0) {
            return $this->check(self::DANGER, 'diagnostics.broken_subscriptions', (string) $count, 'diagnostics.broken_subscriptions.fix', "DELETE FROM calendarsubscriptions WHERE source IS NULL OR source = '';");
        }

        return $this->check(self::OK, 'diagnostics.broken_subscriptions', '0', 'diagnostics.broken_subscriptions.help');
    }

    private function auth(): array
    {
        $value = $this->authMethod;
        if ('basic' === strtolower($this->authMethod)) {
            $value .= ' · realm '.$this->authRealm;
        }

        return $this->check(self::INFO, 'diagnostics.auth', $value, 'basic' === strtolower($this->authMethod) ? 'diagnostics.auth.realm' : null);
    }

    /**
     * ext-ldap and ext-imap are optional for Davis, but the selected method cannot work without
     * the one it needs, and that surfaces as every login being refused.
     */
    private function authExtension(): array
    {
        $required = match (strtolower($this->authMethod)) {
            'ldap' => 'ldap',
            'imap' => 'imap',
            default => null,
        };

        if (null === $required) {
            return $this->check(self::OK, 'diagnostics.auth_extension', 'diagnostics.auth_extension.not_needed');
        }

        if (!\extension_loaded($required)) {
            return $this->check(self::DANGER, 'diagnostics.auth_extension', 'ext-'.$required, 'diagnostics.auth_extension.fix', 'docker-php-ext-install '.$required);
        }

        return $this->check(self::OK, 'diagnostics.auth_extension', 'ext-'.$required);
    }

    private function inviteAddress(): array
    {
        if ($this->calDAVEnabled && !$this->inviteAddress) {
            return $this->check(self::WARNING, 'diagnostics.invite_address', 'diagnostics.value.not_set', 'diagnostics.invite_address.fix', 'INVITE_FROM_ADDRESS=no-reply@example.org');
        }

        return $this->check(self::OK, 'diagnostics.invite_address', $this->inviteAddress ?: 'diagnostics.value.not_set');
    }

    private function mailer(): array
    {
        $scheme = $this->mailerDsn ? parse_url($this->mailerDsn, PHP_URL_SCHEME) : null;
        $host = $this->mailerDsn ? parse_url($this->mailerDsn, PHP_URL_HOST) : null;

        if (!$host) {
            return $this->check(self::WARNING, 'diagnostics.mailer', 'diagnostics.value.not_set', 'diagnostics.mailer.fix', 'MAILER_DSN=smtp://user:pass@smtp.example.com:587');
        }

        // Only the scheme and host: the DSN carries the password.
        return $this->check(self::OK, 'diagnostics.mailer', $scheme.'://'.$host, 'diagnostics.mailer.test', 'php bin/console davis:mail:test you@example.org');
    }

    /**
     * sabre only produces an invitation when the event organiser is one of the account's own
     * calendar addresses, and that list is built from this column.
     */
    private function accountsWithoutEmail(): array
    {
        try {
            $count = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM principals WHERE (email IS NULL OR email = '') AND uri NOT LIKE '%calendar-proxy-%'");
        } catch (\Throwable $e) {
            return $this->check(self::INFO, 'diagnostics.accounts_without_email', $e->getMessage());
        }

        if ($count > 0) {
            return $this->check(
                self::WARNING,
                'diagnostics.accounts_without_email',
                (string) $count,
                'diagnostics.accounts_without_email.fix',
                "SELECT uri, email FROM principals WHERE email IS NULL OR email = '';",
                $this->router->generate('user_index')
            );
        }

        return $this->check(self::OK, 'diagnostics.accounts_without_email', '0', 'diagnostics.accounts_without_email.help');
    }

    private function davEndpoint(): array
    {
        return $this->check(self::INFO, 'diagnostics.dav_endpoint', $this->router->generate('dav', ['path' => ''], UrlGeneratorInterface::ABSOLUTE_URL), 'diagnostics.dav_endpoint.hint');
    }

    private function protocols(): array
    {
        $enabled = [];
        foreach (['CalDAV' => $this->calDAVEnabled, 'CardDAV' => $this->cardDAVEnabled, 'WebDAV' => $this->webDAVEnabled] as $name => $on) {
            if ($on) {
                $enabled[] = $name;
            }
        }

        if (!$enabled) {
            return $this->check(self::DANGER, 'diagnostics.protocols', 'diagnostics.value.none', 'diagnostics.protocols.fix', 'CALDAV_ENABLED=true');
        }

        return $this->check(self::OK, 'diagnostics.protocols', implode(' · ', $enabled));
    }

    /**
     * Who can write to the shared WebDAV directory. A regular user getting 403 when saving a
     * file there is the expected default, and this is the place that says so.
     */
    private function webdavPublicDir(): ?array
    {
        if (!$this->webDAVEnabled) {
            return null;
        }

        if ($this->webdavPublicDirWritable) {
            return $this->check(self::INFO, 'diagnostics.webdav_public_dir', 'diagnostics.webdav_public_dir.everyone', 'diagnostics.webdav_public_dir.everyone.hint');
        }

        return $this->check(self::INFO, 'diagnostics.webdav_public_dir', 'diagnostics.webdav_public_dir.admins', 'diagnostics.webdav_public_dir.admins.hint');
    }
}
