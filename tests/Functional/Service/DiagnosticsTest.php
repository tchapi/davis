<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service;

use App\Services\Diagnostics;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The page is only worth having if a check turns red when its condition holds, so each one is
 * driven here into the state it is meant to report.
 */
class DiagnosticsTest extends KernelTestCase
{
    /**
     * @param array<string, mixed> $overrides
     */
    private function diagnosticsWith(array $overrides = []): Diagnostics
    {
        self::bootKernel();
        $container = static::getContainer();

        $arguments = array_merge([
            'connection' => $container->get('doctrine')->getConnection(),
            'migrations' => $container->get('doctrine.migrations.dependency_factory'),
            'router' => $container->get(UrlGeneratorInterface::class),
            'environment' => 'prod',
            'debug' => false,
            'logFilePath' => sys_get_temp_dir().'/davis-diagnostics/prod.log',
            'timezoneParameter' => 'Europe/Paris',
            'authMethod' => 'Basic',
            'authRealm' => 'SabreDAV',
            'calDAVEnabled' => true,
            'cardDAVEnabled' => true,
            'webDAVEnabled' => false,
            'webdavPublicDirWritable' => false,
            'inviteAddress' => 'no-reply@example.org',
            'mailerDsn' => 'smtp://smtp.example.org:587',
        ], $overrides);

        // Only for the default, writable path: some tests deliberately point somewhere unwritable
        $directory = \dirname($arguments['logFilePath']);
        if (str_starts_with($directory, sys_get_temp_dir()) && !is_dir($directory)) {
            mkdir($directory, 0o777, true);
        }

        return new Diagnostics(...$arguments);
    }

    /**
     * @return array<string, array<string, string|null>> keyed by check title
     */
    private function checksOf(Diagnostics $diagnostics): array
    {
        $checks = [];
        foreach ($diagnostics->buckets() as $bucket) {
            foreach ($bucket['checks'] as $check) {
                $checks[$check['title']] = $check;
            }
        }

        return $checks;
    }

    private function severityOf(Diagnostics $diagnostics, string $title): string
    {
        $checks = $this->checksOf($diagnostics);
        $this->assertArrayHasKey($title, $checks);

        return $checks[$title]['severity'];
    }

    public function testAHealthyInstallReportsNothingToLookAt(): void
    {
        $diagnostics = $this->diagnosticsWith();

        $this->assertSame(Diagnostics::OK, $this->severityOf($diagnostics, 'diagnostics.migrations'));
        $this->assertSame(Diagnostics::OK, $this->severityOf($diagnostics, 'diagnostics.log_file'));
        $this->assertSame(Diagnostics::OK, $this->severityOf($diagnostics, 'diagnostics.environment'));
        $this->assertSame(Diagnostics::OK, $this->severityOf($diagnostics, 'diagnostics.timezone'));
        $this->assertSame(Diagnostics::OK, $this->severityOf($diagnostics, 'diagnostics.accounts_without_email'));
        $this->assertSame(Diagnostics::OK, $this->severityOf($diagnostics, 'diagnostics.broken_subscriptions'));
    }

    public function testAnUnwritableLogDirectoryIsReported(): void
    {
        $diagnostics = $this->diagnosticsWith(['logFilePath' => '/this/path/does/not/exist/prod.log']);

        $this->assertSame(Diagnostics::DANGER, $this->severityOf($diagnostics, 'diagnostics.log_file'));
    }

    public function testRunningOutsideProductionIsReported(): void
    {
        $this->assertSame(
            Diagnostics::WARNING,
            $this->severityOf($this->diagnosticsWith(['environment' => 'dev', 'debug' => true]), 'diagnostics.environment')
        );
    }

    public function testATimezoneThatIsNotOneIsReported(): void
    {
        $this->assertSame(
            Diagnostics::DANGER,
            $this->severityOf($this->diagnosticsWith(['timezoneParameter' => 'Mars/Olympus_Mons']), 'diagnostics.timezone')
        );

        $this->assertSame(
            Diagnostics::WARNING,
            $this->severityOf($this->diagnosticsWith(['timezoneParameter' => '']), 'diagnostics.timezone')
        );
    }

    public function testLdapWithoutItsExtensionIsReported(): void
    {
        if (\extension_loaded('ldap')) {
            $this->markTestSkipped('ext-ldap is loaded here, so it cannot be reported as missing');
        }

        $this->assertSame(
            Diagnostics::DANGER,
            $this->severityOf($this->diagnosticsWith(['authMethod' => 'LDAP']), 'diagnostics.auth_extension')
        );
    }

    public function testBasicAuthDoesNotAskForAnExtension(): void
    {
        $this->assertSame(
            Diagnostics::OK,
            $this->severityOf($this->diagnosticsWith(['authMethod' => 'Basic']), 'diagnostics.auth_extension')
        );
    }

    public function testSchedulingWithoutASenderAddressIsReported(): void
    {
        $this->assertSame(
            Diagnostics::WARNING,
            $this->severityOf($this->diagnosticsWith(['inviteAddress' => null]), 'diagnostics.invite_address')
        );
    }

    public function testAMissingMailTransportIsReported(): void
    {
        $this->assertSame(
            Diagnostics::WARNING,
            $this->severityOf($this->diagnosticsWith(['mailerDsn' => null]), 'diagnostics.mailer')
        );
    }

    public function testNoProtocolEnabledIsReported(): void
    {
        $this->assertSame(
            Diagnostics::DANGER,
            $this->severityOf($this->diagnosticsWith([
                'calDAVEnabled' => false,
                'cardDAVEnabled' => false,
                'webDAVEnabled' => false,
            ]), 'diagnostics.protocols')
        );
    }

    /**
     * The count on the dashboard is what tells an administrator to look at all.
     */
    public function testTheAttentionCountFollowsTheChecks(): void
    {
        // Relative to the baseline: on SQLite the engine check warns on its own, by design.
        $baseline = $this->diagnosticsWith()->attentionCount();

        $this->assertSame($baseline + 1, $this->diagnosticsWith([
            'environment' => 'dev',
            'debug' => true,
        ])->attentionCount());

        $this->assertSame($baseline + 2, $this->diagnosticsWith([
            'environment' => 'dev',
            'debug' => true,
            'inviteAddress' => null,
        ])->attentionCount());
    }

    /**
     * Whatever the state, the page must not put a credential on screen.
     */
    public function testTheMailTransportNeverShowsItsCredentials(): void
    {
        $checks = $this->checksOf($this->diagnosticsWith([
            'mailerDsn' => 'smtp://someone:hunter2@smtp.example.org:587',
        ]));

        $this->assertStringNotContainsString('hunter2', $checks['diagnostics.mailer']['value']);
        $this->assertStringNotContainsString('someone', $checks['diagnostics.mailer']['value']);
        $this->assertSame('smtp://smtp.example.org', $checks['diagnostics.mailer']['value']);
    }
}
