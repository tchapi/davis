<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * WebDAV is disabled in the test environment; these tests enable it per kernel boot
 * (the env() parameters are resolved at runtime) with throw-away directories.
 */
class WebDavTest extends WebTestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir().'/davis-webdav-'.bin2hex(random_bytes(4));
        mkdir($this->baseDir.'/tmp', 0777, true);
        mkdir($this->baseDir.'/public', 0777, true);

        $this->setEnv('WEBDAV_ENABLED', 'true');
        $this->setEnv('WEBDAV_TMP_DIR', $this->baseDir.'/tmp');
        $this->setEnv('WEBDAV_PUBLIC_DIR', $this->baseDir.'/public');
        $this->setEnv('WEBDAV_PUBLIC_DIR_WRITABLE', 'false');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        exec('rm -rf '.escapeshellarg($this->baseDir));
    }

    private function setEnv(string $name, string $value): void
    {
        $_ENV[$name] = $_SERVER[$name] = $value;
        putenv($name.'='.$value);
    }

    private function dav(KernelBrowser $client, string $method, string $path, ?string $userPass = null): void
    {
        DavTest::requestDav($client, $method, $path, $userPass);
    }

    public function testWebdavIsMounted(): void
    {
        $client = static::createClient();
        $this->dav($client, 'PROPFIND', '/dav/public/', 'test_user:password');

        $this->assertResponseStatusCodeSame(207);
    }

    /**
     * Regression test: temporary files (.DS_Store, Thumbs.db, ...) are stored outside the
     * DAV tree, so the ACL plugin never saw them and anybody could write, read and delete
     * them without authenticating.
     */
    public function testAnonymousCannotStoreTemporaryFiles(): void
    {
        $client = static::createClient();
        $this->dav($client, 'PUT', '/dav/public/.DS_Store');

        $this->assertResponseStatusCodeSame(401);
        $this->assertSame([], glob($this->baseDir.'/tmp/sabredav_*'), 'No temporary file must have been written');
    }

    public function testAnonymousCannotReadOrDeleteTemporaryFiles(): void
    {
        $client = static::createClient();
        $this->dav($client, 'PUT', '/dav/public/.DS_Store', 'test_user:password');
        $this->assertResponseStatusCodeSame(201);
        $this->assertCount(1, glob($this->baseDir.'/tmp/sabredav_*'));

        $this->dav($client, 'GET', '/dav/public/.DS_Store');
        $this->assertResponseStatusCodeSame(401);

        $this->dav($client, 'DELETE', '/dav/public/.DS_Store');
        $this->assertResponseStatusCodeSame(401);
        $this->assertCount(1, glob($this->baseDir.'/tmp/sabredav_*'), 'The temporary file must still be there');
    }

    public function testAuthenticatedUserCanUseTemporaryFiles(): void
    {
        $client = static::createClient();

        $this->dav($client, 'PUT', '/dav/public/.DS_Store', 'test_user:password');
        $this->assertResponseStatusCodeSame(201);

        $this->dav($client, 'GET', '/dav/public/.DS_Store', 'test_user:password');
        $this->assertResponseStatusCodeSame(200);
        $this->assertSame('true', $client->getResponse()->headers->get('X-Sabre-Temp'));

        $this->dav($client, 'DELETE', '/dav/public/.DS_Store', 'test_user:password');
        $this->assertResponseStatusCodeSame(204);
        $this->assertSame([], glob($this->baseDir.'/tmp/sabredav_*'));
    }

    public function testWebdavDirectoryInsideTheWebRootIsRefused(): void
    {
        $client = static::createClient();
        $webRootDir = static::getContainer()->getParameter('kernel.project_dir').'/public/webdav-test-'.bin2hex(random_bytes(4));
        mkdir($webRootDir);
        $this->setEnv('WEBDAV_PUBLIC_DIR', $webRootDir);

        try {
            $this->dav($client, 'PROPFIND', '/dav/public/', 'test_user:password');
            $this->assertResponseStatusCodeSame(500);
        } finally {
            rmdir($webRootDir);
        }
    }

    public function testRelativeWebdavDirectoryIsRefused(): void
    {
        $client = static::createClient();
        $this->setEnv('WEBDAV_TMP_DIR', 'var/tmp');

        $this->dav($client, 'PROPFIND', '/dav/public/', 'test_user:password');
        $this->assertResponseStatusCodeSame(500);
    }

    /**
     * Fixtures: test_user is an admin principal, test_user2 is not.
     */
    public function testPublicDirectoryIsReadableByEveryUserButWritableByAdminsOnly(): void
    {
        $client = static::createClient();

        // Regular user: read yes, write no
        $this->dav($client, 'PROPFIND', '/dav/public/', 'test_user2:password2');
        $this->assertResponseStatusCodeSame(207);

        $this->dav($client, 'PUT', '/dav/public/notes.txt', 'test_user2:password2');
        $this->assertResponseStatusCodeSame(403);
        $this->assertFileDoesNotExist($this->baseDir.'/public/notes.txt');

        $this->dav($client, 'MKCOL', '/dav/public/folder', 'test_user2:password2');
        $this->assertResponseStatusCodeSame(403);
        $this->assertDirectoryDoesNotExist($this->baseDir.'/public/folder');

        // Admin: everything
        $this->dav($client, 'PUT', '/dav/public/notes.txt', 'test_user:password');
        $this->assertResponseStatusCodeSame(201);
        $this->assertFileExists($this->baseDir.'/public/notes.txt');

        // Regular user can read what the admin published, but not remove it
        $this->dav($client, 'GET', '/dav/public/notes.txt', 'test_user2:password2');
        $this->assertResponseStatusCodeSame(200);

        $this->dav($client, 'DELETE', '/dav/public/notes.txt', 'test_user2:password2');
        $this->assertResponseStatusCodeSame(403);
        $this->assertFileExists($this->baseDir.'/public/notes.txt');

        $this->dav($client, 'DELETE', '/dav/public/notes.txt', 'test_user:password');
        $this->assertResponseStatusCodeSame(204);
        $this->assertFileDoesNotExist($this->baseDir.'/public/notes.txt');
    }

    public function testPublicDirectoryIsNeverReadableAnonymously(): void
    {
        $client = static::createClient();

        $this->dav($client, 'PROPFIND', '/dav/public/');
        $this->assertResponseStatusCodeSame(401);

        $this->setEnv('WEBDAV_PUBLIC_DIR_WRITABLE', 'true');
        self::ensureKernelShutdown();
        $client = static::createClient();
        $this->dav($client, 'PUT', '/dav/public/notes.txt');
        $this->assertResponseStatusCodeSame(401);
        $this->assertFileDoesNotExist($this->baseDir.'/public/notes.txt');
    }

    public function testPublicDirectoryCanBeOpenedToEveryUser(): void
    {
        $this->setEnv('WEBDAV_PUBLIC_DIR_WRITABLE', 'true');
        $client = static::createClient();

        $this->dav($client, 'PUT', '/dav/public/notes.txt', 'test_user2:password2');
        $this->assertResponseStatusCodeSame(201);

        $this->dav($client, 'MKCOL', '/dav/public/folder', 'test_user2:password2');
        $this->assertResponseStatusCodeSame(201);

        $this->dav($client, 'DELETE', '/dav/public/notes.txt', 'test_user2:password2');
        $this->assertResponseStatusCodeSame(204);
    }

    /**
     * Temporary files follow the ACL of their directory, like real files.
     */
    public function testTemporaryFilesFollowThePublicDirectoryAcl(): void
    {
        $client = static::createClient();
        $this->dav($client, 'PUT', '/dav/public/.DS_Store', 'test_user2:password2');
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame([], glob($this->baseDir.'/tmp/sabredav_*'));

        $this->setEnv('WEBDAV_PUBLIC_DIR_WRITABLE', 'true');
        self::ensureKernelShutdown();
        $client = static::createClient();
        $this->dav($client, 'PUT', '/dav/public/.DS_Store', 'test_user2:password2');
        $this->assertResponseStatusCodeSame(201);
        $this->assertCount(1, glob($this->baseDir.'/tmp/sabredav_*'));
    }
}
