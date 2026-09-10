<?php

namespace App\Tests\Functional;

use App\Entity\CalendarInstance;
use App\Entity\CalendarObject;
use App\Entity\Principal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;

class DavTest extends WebTestCase
{
    private const SECRET_OBJECT_URI = 'secret.ics';
    private const SECRET_SUMMARY = 'Top secret meeting';
    private const SECRET_OBJECT_PATH = '/dav/calendars/test_user/default/'.self::SECRET_OBJECT_URI;

    private const SECRET_CALENDAR_DATA = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Davis//Test//EN\r\nBEGIN:VEVENT\r\nUID:secret-1\r\nDTSTAMP:20260101T100000Z\r\nDTSTART:20260101T100000Z\r\nDTEND:20260101T110000Z\r\nSUMMARY:".self::SECRET_SUMMARY."\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

    /**
     * The DAVController uses a sabre/dav that relies on REQUEST_URI and REQUEST_METHOD
     * which are not set by default by PHPUnit (or to the wrong values).
     * We thus force them here so that the request looks like a real one for PHPUnit.
     *
     * The Authorization header is read by sabre/dav from $_SERVER too, so we set (or unset)
     * it the same way.
     */
    public static function requestDav(AbstractBrowser $client, string $method, string $path, ?string $basicAuthUserPass = null): void
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

    public static function requestDavClient(string $method, string $path): AbstractBrowser
    {
        $client = static::createClient();
        static::requestDav($client, $method, $path);

        return $client;
    }

    /**
     * Stores a calendar object in test_user's default calendar directly in the database,
     * bypassing the DAV server (the test client cannot feed a request body to sabre/dav).
     */
    private function createSecretCalendarObject(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        $instance = $em->getRepository(CalendarInstance::class)->findOneBy([
            'principalUri' => Principal::PREFIX.'test_user',
            'uri' => 'default',
        ]);
        $this->assertNotNull($instance, 'Fixture calendar for test_user is missing');

        $object = (new CalendarObject())
            ->setCalendar($instance->getCalendar())
            ->setUri(self::SECRET_OBJECT_URI)
            ->setCalendarData(self::SECRET_CALENDAR_DATA)
            ->setEtag(md5(self::SECRET_CALENDAR_DATA))
            ->setSize(strlen(self::SECRET_CALENDAR_DATA))
            ->setComponentType('VEVENT')
            ->setUid('secret-1')
            ->setLastModified(time())
            ->setFirstOccurence(strtotime('2026-01-01T10:00:00Z'))
            ->setLastOccurence(strtotime('2026-01-01T11:00:00Z'));

        $em->persist($object);
        $em->flush();
        $em->clear();
    }

    private function getSecretCalendarData(): ?string
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $em->clear();

        $object = $em->getRepository(CalendarObject::class)->findOneBy(['uri' => self::SECRET_OBJECT_URI]);

        return $object?->getCalendarData();
    }

    public function testUnauthorized(): void
    {
        $client = static::requestDavClient('GET', '/dav/');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testEmptyPasswordIsRejected(): void
    {
        $client = static::createClient();

        static::requestDav($client, 'GET', '/dav/', 'test_user:');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testAuthenticatedUserCanReadOwnCalendarObject(): void
    {
        $client = static::createClient();
        $this->createSecretCalendarObject();

        static::requestDav($client, 'GET', self::SECRET_OBJECT_PATH, 'test_user:password');

        $this->assertResponseStatusCodeSame(200);
        $this->assertStringContainsString(self::SECRET_SUMMARY, $client->getResponse()->getContent());
    }

    public function testAnonymousCannotReadCalendarObject(): void
    {
        $client = static::createClient();
        $this->createSecretCalendarObject();

        static::requestDav($client, 'GET', self::SECRET_OBJECT_PATH);

        $this->assertResponseStatusCodeSame(401);
        $this->assertStringNotContainsString(self::SECRET_SUMMARY, $client->getResponse()->getContent());
    }

    /**
     * Regression test: the ACL plugin used to skip all privilege checks whenever
     * `sabreAction=asset` was present in the query string, for any method and any path.
     */
    public function testAssetQueryParameterDoesNotBypassReadAcl(): void
    {
        $client = static::createClient();
        $this->createSecretCalendarObject();

        static::requestDav($client, 'GET', self::SECRET_OBJECT_PATH.'?sabreAction=asset');

        $this->assertResponseStatusCodeSame(401);
        $this->assertStringNotContainsString(self::SECRET_SUMMARY, $client->getResponse()->getContent());
    }

    /**
     * For an authenticated user, sabre does not throw while collecting the GET headers,
     * so without the fix the object of another user is streamed back.
     */
    public function testAssetQueryParameterDoesNotBypassReadAclForOtherUsers(): void
    {
        $client = static::createClient();
        $this->createSecretCalendarObject();

        static::requestDav($client, 'GET', self::SECRET_OBJECT_PATH.'?sabreAction=asset', 'test_user2:password2');

        $this->assertResponseStatusCodeSame(403);
        $this->assertStringNotContainsString(self::SECRET_SUMMARY, $client->getResponse()->getContent());
    }

    public function testAssetQueryParameterDoesNotBypassWriteAcl(): void
    {
        $client = static::createClient();
        $this->createSecretCalendarObject();

        static::requestDav($client, 'PUT', self::SECRET_OBJECT_PATH.'?sabreAction=asset');

        $this->assertResponseStatusCodeSame(401);
        $this->assertSame(self::SECRET_CALENDAR_DATA, $this->getSecretCalendarData(), 'The calendar object must not have been modified');
    }

    public function testAssetQueryParameterDoesNotBypassAclOnCollections(): void
    {
        $client = static::createClient();

        static::requestDav($client, 'GET', '/dav/calendars/test_user/default/?sabreAction=asset&assetName=favicon.ico');

        $this->assertResponseStatusCodeSame(401);
    }

    /**
     * The only legitimate use of the bypass: the browser plugin loads its assets from the
     * server root, and they must be reachable anonymously for public calendar pages.
     */
    public function testBrowserAssetsAreServedAnonymouslyFromRoot(): void
    {
        $client = static::createClient();

        static::requestDav($client, 'GET', '/dav/?sabreAction=asset&assetName=favicon.ico');

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame(
            file_get_contents(static::getContainer()->getParameter('kernel.project_dir').'/vendor/sabre/dav/lib/DAV/Browser/assets/favicon.ico'),
            $client->getResponse()->getContent()
        );
    }

    public function testWellKnownUrlsRedirectToTheDavEndpoint(): void
    {
        $client = static::createClient();

        foreach (['/.well-known/caldav', '/.well-known/carddav'] as $wellKnown) {
            $client->request('GET', $wellKnown);

            $this->assertResponseStatusCodeSame(301, $wellKnown.' should redirect');
            $this->assertResponseRedirects('/dav/');
        }
    }

    /**
     * OPTIONS used to answer for the server root whatever was asked, so it never advertised
     * the methods that only exist deeper in the tree, MKCALENDAR being the obvious one.
     */
    public function testOptionsDescribesTheRequestedPath(): void
    {
        $client = static::createClient();

        static::requestDav($client, 'OPTIONS', '/dav/');
        $this->assertResponseIsSuccessful();
        $this->assertStringNotContainsString('MKCALENDAR', (string) $client->getResponse()->headers->get('Allow'));

        static::requestDav($client, 'OPTIONS', '/dav/calendars/test_user/new-calendar');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('MKCALENDAR', (string) $client->getResponse()->headers->get('Allow'));
    }

    public function testOptionsOnAnUnresolvablePathStillAnswers(): void
    {
        $client = static::createClient();

        static::requestDav($client, 'OPTIONS', '/dav/calendars/nope/nope/nope');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('PROPFIND', (string) $client->getResponse()->headers->get('Allow'));
    }
}
