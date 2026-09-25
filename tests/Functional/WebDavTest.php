<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * WebDAV is disabled in the test environment; these tests enable it with throw-away
 * directories. The env() parameters are resolved when the DAVController service is built,
 * and the test client reboots the kernel before every request, so a setEnv() call takes
 * effect on the very next request without any explicit kernel shutdown.
 */
class WebDavTest extends WebTestCase
{
    use DavRequestTrait;

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

        (new Filesystem())->remove($this->baseDir);
    }

    private function setEnv(string $name, string $value): void
    {
        $_ENV[$name] = $_SERVER[$name] = $value;
        putenv($name.'='.$value);
    }

    public function testWebdavIsMounted(): void
    {
        $client = static::createClient();
        static::requestDav($client, 'PROPFIND', '/dav/public/', 'test_user:password');

        $this->assertResponseStatusCodeSame(207);
        // The headers sabre sets must survive the conversion to a Symfony response
        $this->assertResponseHeaderSame('Content-Type', 'application/xml; charset=utf-8');
    }

    /**
     * Regression test: temporary files (.DS_Store, Thumbs.db, ...) are stored outside the
     * DAV tree, so the ACL plugin never saw them and anybody could write, read and delete
     * them without authenticating.
     */
    public function testAnonymousCannotStoreTemporaryFiles(): void
    {
        $client = static::createClient();
        static::requestDav($client, 'PUT', '/dav/public/.DS_Store');

        $this->assertResponseStatusCodeSame(401);
        $this->assertSame([], glob($this->baseDir.'/tmp/sabredav_*'), 'No temporary file must have been written');
    }

    public function testAnonymousCannotReadOrDeleteTemporaryFiles(): void
    {
        $client = static::createClient();
        static::requestDav($client, 'PUT', '/dav/public/.DS_Store', 'test_user:password');
        $this->assertResponseStatusCodeSame(201);
        $this->assertCount(1, glob($this->baseDir.'/tmp/sabredav_*'));

        static::requestDav($client, 'GET', '/dav/public/.DS_Store');
        $this->assertResponseStatusCodeSame(401);

        static::requestDav($client, 'DELETE', '/dav/public/.DS_Store');
        $this->assertResponseStatusCodeSame(401);
        $this->assertCount(1, glob($this->baseDir.'/tmp/sabredav_*'), 'The temporary file must still be there');
    }

    public function testAuthenticatedUserCanUseTemporaryFiles(): void
    {
        $client = static::createClient();

        static::requestDav($client, 'PUT', '/dav/public/.DS_Store', 'test_user:password');
        $this->assertResponseStatusCodeSame(201);

        static::requestDav($client, 'GET', '/dav/public/.DS_Store', 'test_user:password');
        $this->assertResponseStatusCodeSame(200);
        $this->assertSame('true', $client->getResponse()->headers->get('X-Sabre-Temp'));

        static::requestDav($client, 'DELETE', '/dav/public/.DS_Store', 'test_user:password');
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
            static::requestDav($client, 'PROPFIND', '/dav/public/', 'test_user:password');
            $this->assertResponseStatusCodeSame(500);
        } finally {
            rmdir($webRootDir);
        }
    }

    public function testRelativeWebdavDirectoryIsRefused(): void
    {
        $client = static::createClient();
        $this->setEnv('WEBDAV_TMP_DIR', 'var/tmp');

        static::requestDav($client, 'PROPFIND', '/dav/public/', 'test_user:password');
        $this->assertResponseStatusCodeSame(500);
    }

    /**
     * The tmp dir holds the locks database and the temporary files, the homes dir holds
     * other users' files: none of them may be reachable through the public directory.
     */
    public function testWebdavDirectoriesNestedInThePublicDirectoryAreRefused(): void
    {
        $client = static::createClient();

        mkdir($this->baseDir.'/public/tmp');
        $this->setEnv('WEBDAV_TMP_DIR', $this->baseDir.'/public/tmp');
        static::requestDav($client, 'PROPFIND', '/dav/public/', 'test_user:password');
        $this->assertResponseStatusCodeSame(500);

        $this->setEnv('WEBDAV_TMP_DIR', $this->baseDir.'/tmp');
        mkdir($this->baseDir.'/public/homes');
        $this->setEnv('WEBDAV_HOMES_DIR', $this->baseDir.'/public/homes');
        static::requestDav($client, 'PROPFIND', '/dav/public/', 'test_user:password');
        $this->assertResponseStatusCodeSame(500);

        // Siblings are fine
        mkdir($this->baseDir.'/homes');
        $this->setEnv('WEBDAV_HOMES_DIR', $this->baseDir.'/homes');
        static::requestDav($client, 'PROPFIND', '/dav/public/', 'test_user:password');
        $this->assertResponseStatusCodeSame(207);
    }

    /**
     * Fixtures: test_user is an admin principal, test_user2 is not.
     */
    public function testPublicDirectoryIsReadableByEveryUserButWritableByAdminsOnly(): void
    {
        $client = static::createClient();

        // Regular user: read yes, write no
        static::requestDav($client, 'PROPFIND', '/dav/public/', 'test_user2:password2');
        $this->assertResponseStatusCodeSame(207);

        static::requestDav($client, 'PUT', '/dav/public/notes.txt', 'test_user2:password2');
        $this->assertResponseStatusCodeSame(403);
        $this->assertFileDoesNotExist($this->baseDir.'/public/notes.txt');

        static::requestDav($client, 'MKCOL', '/dav/public/folder', 'test_user2:password2');
        $this->assertResponseStatusCodeSame(403);
        $this->assertDirectoryDoesNotExist($this->baseDir.'/public/folder');

        // Admin: everything
        static::requestDav($client, 'PUT', '/dav/public/notes.txt', 'test_user:password');
        $this->assertResponseStatusCodeSame(201);
        $this->assertFileExists($this->baseDir.'/public/notes.txt');

        // Regular user can read what the admin published, but not remove it
        static::requestDav($client, 'GET', '/dav/public/notes.txt', 'test_user2:password2');
        $this->assertResponseStatusCodeSame(200);

        static::requestDav($client, 'DELETE', '/dav/public/notes.txt', 'test_user2:password2');
        $this->assertResponseStatusCodeSame(403);
        $this->assertFileExists($this->baseDir.'/public/notes.txt');

        static::requestDav($client, 'DELETE', '/dav/public/notes.txt', 'test_user:password');
        $this->assertResponseStatusCodeSame(204);
        $this->assertFileDoesNotExist($this->baseDir.'/public/notes.txt');
    }

    public function testPublicDirectoryIsNeverReadableAnonymously(): void
    {
        $client = static::createClient();

        static::requestDav($client, 'PROPFIND', '/dav/public/');
        $this->assertResponseStatusCodeSame(401);
        $this->assertStringStartsWith('Basic realm="', $client->getResponse()->headers->get('WWW-Authenticate') ?? '');

        $this->setEnv('WEBDAV_PUBLIC_DIR_WRITABLE', 'true');
        static::requestDav($client, 'PUT', '/dav/public/notes.txt');
        $this->assertResponseStatusCodeSame(401);
        $this->assertFileDoesNotExist($this->baseDir.'/public/notes.txt');
    }

    public function testPublicDirectoryCanBeOpenedToEveryUser(): void
    {
        $this->setEnv('WEBDAV_PUBLIC_DIR_WRITABLE', 'true');
        $client = static::createClient();

        static::requestDav($client, 'PUT', '/dav/public/notes.txt', 'test_user2:password2');
        $this->assertResponseStatusCodeSame(201);

        static::requestDav($client, 'MKCOL', '/dav/public/folder', 'test_user2:password2');
        $this->assertResponseStatusCodeSame(201);

        static::requestDav($client, 'DELETE', '/dav/public/notes.txt', 'test_user2:password2');
        $this->assertResponseStatusCodeSame(204);
    }

    /**
     * Temporary files follow the ACL of their directory, like real files.
     */
    public function testTemporaryFilesFollowThePublicDirectoryAcl(): void
    {
        $client = static::createClient();
        static::requestDav($client, 'PUT', '/dav/public/.DS_Store', 'test_user2:password2');
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame([], glob($this->baseDir.'/tmp/sabredav_*'));

        $this->setEnv('WEBDAV_PUBLIC_DIR_WRITABLE', 'true');
        static::requestDav($client, 'PUT', '/dav/public/.DS_Store', 'test_user2:password2');
        $this->assertResponseStatusCodeSame(201);
        $this->assertCount(1, glob($this->baseDir.'/tmp/sabredav_*'));
    }
}
