<?php

namespace App\Controller;

use App\Entity\Principal;
use App\Entity\User;
use App\Plugins\DavisIMipPlugin;
use App\Services\BasicAuth;
use App\Services\IMAPAuth;
use App\Services\LDAPAuth;
use Doctrine\DBAL\Connection as DoctrineConnection;
use Doctrine\ORM\EntityManagerInterface;
use PDO;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
     * WebDAV Temporary directory.
     *
     * @var string
     */
    protected $webdavTmpDir;

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var MailerInterface
     */
    protected $mailer;

    /**
     * Base URI of the server.
     *
     * @var string
     */
    protected $baseUri;

    /**
     * Basic Auth Backend class.
     *
     * @var \App\Services\BasicAuth
     */
    protected $basicAuthBackend;

    /**
     * IMAP Auth Backend class.
     *
     * @var \App\Services\IMAPAuth
     */
    protected $IMAPAuthBackend;

    /**
     * LDAP Auth Backend class.
     *
     * @var \App\Services\LDAPAuth
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

    public function __construct(MailerInterface $mailer, BasicAuth $basicAuthBackend, IMAPAuth $IMAPAuthBackend, LDAPAuth $LDAPAuthBackend, UrlGeneratorInterface $router, EntityManagerInterface $entityManager, LoggerInterface $logger, string $publicDir, bool $calDAVEnabled = true, bool $cardDAVEnabled = true, bool $webDAVEnabled = false, ?string $inviteAddress = null, ?string $authMethod = null, ?string $authRealm = null, ?string $webdavPublicDir = null, ?string $webdavTmpDir = null)
    {
        $this->publicDir = $publicDir;

        $this->calDAVEnabled = $calDAVEnabled;
        $this->cardDAVEnabled = $cardDAVEnabled;
        $this->webDAVEnabled = $webDAVEnabled;
        $this->inviteAddress = $inviteAddress ?? null;

        $this->webdavPublicDir = $webdavPublicDir;
        $this->webdavTmpDir = $webdavTmpDir;

        $this->em = $entityManager;
        $this->logger = $logger;
        $this->mailer = $mailer;
        $this->baseUri = $router->generate('dav', ['path' => '']);

        $this->basicAuthBackend = $basicAuthBackend;
        $this->IMAPAuthBackend = $IMAPAuthBackend;
        $this->LDAPAuthBackend = $LDAPAuthBackend;

        $this->initServer($authMethod, $authRealm);
        $this->initExceptionListener();
    }

    /**
     * @Route("/", name="home")
     */
    public function home()
    {
        return $this->render('index.html.twig', [
            'version' => \App\Version::VERSION,
        ]);
    }

    private function initServer(string $authMethod, string $authRealm = User::DEFAULT_AUTH_REALM)
    {
        // Get the PDO Connection of type PDO
        // TODO: Once we drop support for PHP < 8.0 and force dbal > 3.3,
        // We can use getNativeConnection() instead of the deprecated
        // getWrappedConnection() here, and remove the `if`.
        if (method_exists(DoctrineConnection::class, 'getNativeConnection')) {
            $pdo = $this->em->getConnection()->getNativeConnection();
        } else {
            $pdo = $this->em->getConnection()->getWrappedConnection();
        }
        if (!($pdo instanceof PDO)) {
            $pdo = $pdo->getNativeConnection();
        }

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

        if ($this->calDAVEnabled) {
            $calendarBackend = new \Sabre\CalDAV\Backend\PDO($pdo);
            $nodes[] = new \Sabre\CalDAV\CalendarRoot($principalBackend, $calendarBackend);
        }
        if ($this->cardDAVEnabled) {
            $carddavBackend = new \Sabre\CardDAV\Backend\PDO($pdo);
            $nodes[] = new \Sabre\CardDAV\AddressBookRoot($principalBackend, $carddavBackend);
        }
        if ($this->webDAVEnabled && $this->webdavTmpDir && $this->webdavPublicDir) {
            $nodes[] = new \Sabre\DAV\FS\Directory($this->webdavPublicDir);
        }

        // The object tree needs in turn to be passed to the server class
        $this->server = new \Sabre\DAV\Server($nodes);
        $this->server->setBaseUri($this->baseUri);

        // Plugins
        $this->server->addPlugin(new \Sabre\DAV\Auth\Plugin($authBackend, $authRealm));
        $this->server->addPlugin(new \Sabre\DAV\Browser\Plugin());
        $this->server->addPlugin(new \Sabre\DAV\Sync\Plugin());

        $aclPlugin = new \Sabre\DAVACL\Plugin();
        $aclPlugin->hideNodesFromListings = true;
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
            if ($this->inviteAddress) {
                $this->server->addPlugin(new DavisIMipPlugin($this->mailer, $this->inviteAddress, $this->publicDir));
            }
        }

        // CardDAV plugins
        if ($this->cardDAVEnabled) {
            $this->server->addPlugin(new \Sabre\CardDAV\Plugin());
            $this->server->addPlugin(new \Sabre\CardDAV\VCFExportPlugin());
        }

        // WebDAV plugins
        if ($this->webDAVEnabled && $this->webdavTmpDir && $this->webdavPublicDir) {
            if (!is_dir($this->webdavTmpDir) || !is_dir($this->webdavPublicDir)) {
                throw new \Exception('The WebDAV temp dir and/or public dir are not available. Make sure they are created with the correct permissions.');
            }
            $lockBackend = new \Sabre\DAV\Locks\Backend\File($this->webdavTmpDir.'/locksdb');
            $this->server->addPlugin(new \Sabre\DAV\Locks\Plugin($lockBackend));
            $this->server->addPlugin(new \Sabre\DAV\Browser\GuessContentType());
            $this->server->addPlugin(new \Sabre\DAV\TemporaryFileFilterPlugin($this->webdavTmpDir));
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
     * @Route("/dav/{path}", name="dav", requirements={"path":".*"})
     */
    public function dav(Request $request, string $path)
    {
        // \Sabre\DAV\Server does not let us use a custom SAPI, and its behaviour
        // is to directly output headers and content to php://output. Hence, we
        // let the headers pass (we have not choice) and capture the output in a
        // buffer.
        // This allows us to use a Response, and not to break the events triggered
        // by Symfony after the response is sent, like for instance the TERMINATE
        // event from the Kernel, that is used to send emails...

        ob_start(); // Does not capture headers!
        $this->server->start();

        $output = ob_get_contents();
        ob_end_clean();

        // As previously said, headers are already _prepared_ by the server,
        // so we can't modify them or remove them. But they are not _sent_ yet,
        // so headers_sent() is false, and Symfony will add its own headers above it.
        //
        // The Content-type header is the problem, since Symfony will
        // output `text/html` for everything since it doesn't know any better.
        // Thus, we have to get the _real_ Content-type header already prepared,
        // and force it in the Symfony Response.
        //
        // That's what we do here.
        $response = new Response($output, http_response_code(), []);
        foreach (headers_list() as $header) {
            if ('content-type:' === strtolower(substr($header, 0, 13))) {
                $headerArray = explode(':', $header);
                $response->headers->set('Content-type', $headerArray[1]);
            }
        }

        return $response;
    }
}
