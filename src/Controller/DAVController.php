<?php

namespace App\Controller;

use App\Entity\Principal;
use App\Entity\User;
use App\Plugins\BirthdayCalendarPlugin;
use App\Plugins\DavisIMipPlugin;
use App\Plugins\DavisTemporaryFileFilterPlugin;
use App\Plugins\PublicAwareDAVACLPlugin;
use App\Services\BasicAuth;
use App\Services\BirthdayService;
use App\Services\CapturingSapi;
use App\Services\IMAPAuth;
use App\Services\LDAPAuth;
use Doctrine\ORM\EntityManagerInterface;
use PDO;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class DAVController extends AbstractController
{
    public const AUTH_BASIC = 'Basic';
    public const AUTH_IMAP = 'IMAP';
    public const AUTH_LDAP = 'LDAP';

    /**
     * Is CalDAV enabled?
     *
     * @var bool
     */
    protected $calDAVEnabled;

    /**
     * is CardDAV enabled?
     *
     * @var bool
     */
    protected $cardDAVEnabled;

    /**
     * is WebDAV enabled?
     *
     * @var bool
     */
    protected $webDAVEnabled;

    /**
     * Are public calendars enabled?
     *
     * @var bool
     */
    protected $publicCalendarsEnabled;

    /**
     * Mail address to send mails from.
     *
     * @var string
     */
    protected $inviteAddress;

    /**
     * Public directory of the Symfony installation.
     * Needed to retrieve assets (images).
     *
     * @var string
     */
    protected $publicDir;

    /**
     * WebDAV Public directory.
     *
     * @var string
     */
    protected $webdavPublicDir;

    /**
     * WebDAV User Homes directory.
     *
     * @var string|null
     */
    protected $webdavHomesDir;

    /**
     * WebDAV Temporary directory.
     *
     * @var string
     */
    protected $webdavTmpDir;

    /**
     * Can every authenticated user write to the WebDAV public directory
     * (otherwise only admins can, everybody can read).
     *
     * @var bool
     */
    protected $webdavPublicDirWritable;

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var MailerInterface
     */
    protected $mailer;

    /**
     * @var BirthdayService
     */
    protected $birthdayService;

    /**
     * Base URI of the server.
     *
     * @var string
     */
    protected $baseUri;

    /**
     * Basic Auth Backend class.
     *
     * @var BasicAuth
     */
    protected $basicAuthBackend;

    /**
     * IMAP Auth Backend class.
     *
     * @var IMAPAuth
     */
    protected $IMAPAuthBackend;

    /**
     * LDAP Auth Backend class.
     *
     * @var LDAPAuth
     */
    protected $LDAPAuthBackend;

    /**
     * Logger for exceptions.
     *
     * @var Psr\Log\LoggerInterface;
     */
    protected $logger;

    /**
     * Server.
     *
     * @var \Sabre\DAV\Server
     */
    protected $server;

    public function __construct(MailerInterface $mailer, BasicAuth $basicAuthBackend, IMAPAuth $IMAPAuthBackend, LDAPAuth $LDAPAuthBackend, UrlGeneratorInterface $router, EntityManagerInterface $entityManager, LoggerInterface $logger, BirthdayService $birthdayService, string $publicDir, bool $calDAVEnabled = true, bool $cardDAVEnabled = true, bool $webDAVEnabled = false, bool $publicCalendarsEnabled = true, ?string $inviteAddress = null, ?string $authMethod = null, ?string $authRealm = null, ?string $webdavPublicDir = null, ?string $webdavHomesDir = null, ?string $webdavTmpDir = null, bool $webdavPublicDirWritable = false)
    {
        $this->publicDir = $publicDir;

        $this->calDAVEnabled = $calDAVEnabled;
        $this->cardDAVEnabled = $cardDAVEnabled;
        $this->webDAVEnabled = $webDAVEnabled;
        $this->publicCalendarsEnabled = $publicCalendarsEnabled;
        $this->inviteAddress = $inviteAddress ?? null;

        $this->webdavPublicDir = $webdavPublicDir;
        $this->webdavHomesDir = $webdavHomesDir;
        $this->webdavTmpDir = $webdavTmpDir;
        $this->webdavPublicDirWritable = $webdavPublicDirWritable;

        $this->em = $entityManager;
        $this->logger = $logger;
        $this->mailer = $mailer;
        $this->birthdayService = $birthdayService;
        $this->baseUri = $router->generate('dav', ['path' => '']);

        $this->basicAuthBackend = $basicAuthBackend;
        $this->IMAPAuthBackend = $IMAPAuthBackend;
        $this->LDAPAuthBackend = $LDAPAuthBackend;

        $this->initServer($authMethod, $authRealm);
        $this->initExceptionListener();
    }

    #[Route('/', name: 'home')]
    public function home(): Response
    {
        return $this->render('index.html.twig', [
            'version' => \App\Version::VERSION,
        ]);
    }

    private function initServer(string $authMethod, string $authRealm = User::DEFAULT_AUTH_REALM)
    {
        // Don't advertise the exact sabre/dav version: it appears in the `X-Sabre-Version`
        // header, in the `<s:sabredav-version>` element of every error body and in the HTML
        // browser, which only helps someone matching an installation against known advisories.
        \Sabre\DAV\Server::$exposeVersion = false;

        // Get the PDO Connection of type PDO
        $pdo = $this->em->getConnection()->getNativeConnection();

        /*
         * The backends.
         */
        switch ($authMethod) {
            case self::AUTH_IMAP:
                $authBackend = $this->IMAPAuthBackend;
                break;
            case self::AUTH_LDAP:
                $authBackend = $this->LDAPAuthBackend;
                break;
            case self::AUTH_BASIC:
            default:
                $authBackend = $this->basicAuthBackend;
                break;
        }

        $authBackend->setRealm($authRealm);

        $principalBackend = new \Sabre\DAVACL\PrincipalBackend\PDO($pdo);

        /**
         * The directory tree.
         *
         * Basically this is an array which contains the 'top-level' directories in the
         * WebDAV server.
         */
        $nodes = [
            // /principals
            new \Sabre\CalDAV\Principal\Collection($principalBackend),
        ];

        if ($this->webdavHomesDir) {
            $this->assertWebdavDirectory($this->webdavHomesDir, 'WEBDAV_HOMES_DIR');
            $nodes[] = new \Sabre\DAVACL\FS\HomeCollection($principalBackend, $this->webdavHomesDir);
        }

        if ($this->calDAVEnabled) {
            $calendarBackend = new \Sabre\CalDAV\Backend\PDO($pdo);
            $nodes[] = new \Sabre\CalDAV\CalendarRoot($principalBackend, $calendarBackend);
        }
        if ($this->cardDAVEnabled) {
            $carddavBackend = new \Sabre\CardDAV\Backend\PDO($pdo);
            $nodes[] = new \Sabre\CardDAV\AddressBookRoot($principalBackend, $carddavBackend);
        }
        if ($this->webDAVEnabled && $this->webdavTmpDir && $this->webdavPublicDir) {
            $this->assertWebdavDirectory($this->webdavTmpDir, 'WEBDAV_TMP_DIR');
            $this->assertWebdavDirectory($this->webdavPublicDir, 'WEBDAV_PUBLIC_DIR');
            // Temporary files and the locks database would be served as regular files if the tmp
            // dir lived inside the public one, and users could browse other users' homes if the
            // homes dir did.
            $this->assertWebdavDirectoriesAreDisjoint($this->webdavPublicDir, 'WEBDAV_PUBLIC_DIR', $this->webdavTmpDir, 'WEBDAV_TMP_DIR');
            if ($this->webdavHomesDir) {
                $this->assertWebdavDirectoriesAreDisjoint($this->webdavPublicDir, 'WEBDAV_PUBLIC_DIR', $this->webdavHomesDir, 'WEBDAV_HOMES_DIR');
            }

            // Explicit ACL for the shared directory: every authenticated user can read it, and
            // writing is reserved to admins (the ACL plugin grants them every privilege) unless
            // WEBDAV_PUBLIC_DIR_WRITABLE opens it to everyone. Children inherit this ACL.
            $publicDirAcl = [
                ['principal' => '{DAV:}authenticated', 'privilege' => '{DAV:}read', 'protected' => true],
            ];
            if ($this->webdavPublicDirWritable) {
                $publicDirAcl[] = ['principal' => '{DAV:}authenticated', 'privilege' => '{DAV:}write', 'protected' => true];
            }
            $nodes[] = new \Sabre\DAVACL\FS\Collection($this->webdavPublicDir, $publicDirAcl);
        }

        // The object tree needs in turn to be passed to the server class. The SAPI keeps
        // sabre from writing to the output itself: dav() turns its response into a Symfony one.
        $this->server = new \Sabre\DAV\Server($nodes, new CapturingSapi());
        $this->server->setBaseUri($this->baseUri);

        // Plugins
        $this->server->addPlugin(new \Sabre\DAV\Auth\Plugin($authBackend, $authRealm));
        $this->server->addPlugin(new \Sabre\DAV\Browser\Plugin(false)); // We disable the file creation / upload / sharing in the browser
        $this->server->addPlugin(new \Sabre\DAV\Sync\Plugin());

        $aclPlugin = new PublicAwareDAVACLPlugin($this->em, $this->publicCalendarsEnabled);
        $aclPlugin->hideNodesFromListings = true;
        $aclPlugin->allowUnauthenticatedAccess = true; // Already the default, but setting it is future-proof

        // Fetch admins, if any
        $admins = $this->em->getRepository(Principal::class)->findBy(['isAdmin' => true]);
        foreach ($admins as $principal) {
            $aclPlugin->adminPrincipals[] = $principal->getUri();
        }

        $this->server->addPlugin($aclPlugin);

        $this->server->addPlugin(new \Sabre\DAV\PropertyStorage\Plugin(
            new \Sabre\DAV\PropertyStorage\Backend\PDO($pdo)
        ));

        // CalDAV plugins
        if ($this->calDAVEnabled) {
            $this->server->addPlugin(new \Sabre\DAV\Sharing\Plugin());
            $this->server->addPlugin(new \Sabre\CalDAV\Plugin());
            $this->server->addPlugin(new \Sabre\CalDAV\Schedule\Plugin());
            $this->server->addPlugin(new \Sabre\CalDAV\SharingPlugin());
            $this->server->addPlugin(new \Sabre\CalDAV\ICSExportPlugin());
            $this->server->addPlugin(new \Sabre\CalDAV\Subscriptions\Plugin());
            if ($this->inviteAddress) {
                $this->server->addPlugin(new DavisIMipPlugin($this->mailer, $this->inviteAddress, $this->publicDir, $this->logger));
            } else {
                // Without it the scheduling plugin above still answers, but no invitation ever
                // leaves the server and nothing says so.
                $this->logger->warning('CalDAV scheduling is enabled but INVITE_FROM_ADDRESS is not set: no invitation email will be sent.');
            }
        }

        // CardDAV plugins
        if ($this->cardDAVEnabled) {
            $this->server->addPlugin(new \Sabre\CardDAV\Plugin());
            $this->server->addPlugin(new \Sabre\CardDAV\VCFExportPlugin());
        }

        if ($this->cardDAVEnabled && $this->calDAVEnabled) {
            $this->server->addPlugin(new BirthdayCalendarPlugin($this->birthdayService, $calendarBackend));
        }

        // WebDAV plugins
        if ($this->webDAVEnabled && $this->webdavTmpDir && $this->webdavPublicDir) {
            $lockBackend = new \Sabre\DAV\Locks\Backend\File($this->webdavTmpDir.'/locksdb');
            $this->server->addPlugin(new \Sabre\DAV\Locks\Plugin($lockBackend));
            $this->server->addPlugin(new \Sabre\DAV\Browser\GuessContentType());
            // Temporary files must obey the ACL of their directory (see the plugin for the why)
            $this->server->addPlugin(new DavisTemporaryFileFilterPlugin($this->webdavTmpDir));
        }
    }

    /**
     * A WebDAV directory must exist, be given as an absolute path (a relative one would be
     * resolved against the PHP process' working directory, which is not predictable) and
     * must not live inside the web root, where the web server would serve its content
     * directly and bypass every DAV permission check.
     */
    private function assertWebdavDirectory(string $dir, string $envVar): string
    {
        if (!Path::isAbsolute($dir)) {
            throw new \RuntimeException(sprintf('%s must be an absolute path, "%s" given.', $envVar, $dir));
        }

        $realDir = realpath($dir);
        if (false === $realDir || !is_dir($realDir)) {
            throw new \RuntimeException(sprintf('%s points to "%s", which does not exist or is not a directory. Make sure it is created with the correct permissions.', $envVar, $dir));
        }

        $webRoot = realpath($this->publicDir);
        if (false !== $webRoot && Path::isBasePath($webRoot, $realDir)) {
            throw new \RuntimeException(sprintf('%s ("%s") must not be inside the web root ("%s"): the web server would serve these files without any permission check.', $envVar, $dir, $webRoot));
        }

        return $realDir;
    }

    /**
     * Neither directory may be the other one or live inside it. Both must already have
     * passed assertWebdavDirectory(), so they exist and realpath() resolves them.
     */
    private function assertWebdavDirectoriesAreDisjoint(string $dirA, string $envVarA, string $dirB, string $envVarB): void
    {
        $realA = realpath($dirA);
        $realB = realpath($dirB);

        if (Path::isBasePath($realA, $realB) || Path::isBasePath($realB, $realA)) {
            throw new \RuntimeException(sprintf('%s ("%s") and %s ("%s") must be separate directories, one must not be inside the other.', $envVarA, $dirA, $envVarB, $dirB));
        }
    }

    private function initExceptionListener()
    {
        $this->server->on('exception', function (\Throwable $e) {
            // We don't need a trace for simple authentication exceptions
            if ($e instanceof \Sabre\DAV\Exception\NotAuthenticated) {
                $this->logger->warning('[401]: '.get_class($e)." - No 'Authorization: Basic' header found. Login was needed");

                return;
            }

            $httpCode = ($e instanceof \Sabre\DAV\Exception) ? $e->getHTTPCode() : 500;
            $this->logger->error('['.$httpCode.']: '.get_class($e).' - '.$e->getMessage(), $e->getTrace());
        });
    }

    /**
     * Service discovery (RFC 6764).
     *
     * This lives in the application rather than in each web server's configuration so that
     * the redirect is built from the real base path: a hard-coded `/dav/` sends clients to
     * the wrong place whenever Davis is installed under a sub-directory.
     */
    #[Route('/.well-known/caldav', name: 'well_known_caldav')]
    #[Route('/.well-known/carddav', name: 'well_known_carddav')]
    public function wellKnown(): Response
    {
        return $this->redirectToRoute('dav', ['path' => ''], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/dav/{path}', name: 'dav', requirements: ['path' => '.*'])]
    public function dav(Request $request, ?string $path, ?Profiler $profiler = null)
    {
        // We don't want the toolbar on the /dav/* routes
        if ($profiler instanceof Profiler) {
            $profiler->disable();
        }

        // We need to acknowledge the OPTIONS call before sabre/dav for public
        // calendars since we're circumventing the lib
        if ('OPTIONS' === $request->getMethod()) {
            $response = new Response();

            // Adapted from CorePlugin's httpOptions()
            // https://github.com/sabre-io/dav/blob/master/lib/DAV/CorePlugin.php#L210
            //
            // The methods depend on the node being asked about: MKCALENDAR, for instance, is
            // only offered inside a calendar home. Answering for the root instead of the
            // requested path told every client the same, incomplete story.
            try {
                $methods = $this->server->getAllowedMethods($path ?? '');
            } catch (\Throwable $e) {
                // An unresolvable path should still get a usable answer
                $methods = $this->server->getAllowedMethods('');
            }

            $response->headers->set('Allow', strtoupper(implode(', ', $methods)));
            $features = ['1', '3', 'extended-mkcol'];

            foreach ($this->server->getPlugins() as $plugin) {
                $features = array_merge($features, $plugin->getFeatures());
            }

            $response->headers->set('DAV', implode(', ', $features));
            $response->headers->set('MS-Author-Via', 'DAV');

            return $response;
        }

        // The server runs the request but, thanks to CapturingSapi, sends nothing: status,
        // headers and body are all still in its response object, whether a method handler
        // produced them, a plugin short-circuited the request from `beforeMethod` (the
        // temporary file filter does) or the server caught an exception.
        $this->server->start();
        $sabreResponse = $this->server->httpResponse;
        $body = $sabreResponse->getBody();

        if (is_resource($body)) {
            // Stream file contents instead of buffering them in memory. sabre honours
            // Content-Length when it streams (range requests rely on it), so do the same.
            $length = $sabreResponse->getHeader('Content-Length');
            $response = new StreamedResponse(function () use ($body, $length): void {
                $output = fopen('php://output', 'wb');
                stream_copy_to_stream($body, $output, null === $length ? null : (int) $length);
                fclose($body);
            }, $sabreResponse->getStatus());
        } elseif (is_callable($body)) {
            // Some plugins hand over a closure that writes straight to php://output
            $response = new StreamedResponse($body, $sabreResponse->getStatus());
        } else {
            $response = new Response($sabreResponse->getBodyAsString(), $sabreResponse->getStatus());
        }

        foreach ($sabreResponse->getHeaders() as $name => $values) {
            $response->headers->set($name, $values);
        }

        return $response;
    }
}
