<?php

namespace App\Controller;

use App\Entity\User;
use App\Services\BasicAuth;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class DAVController extends AbstractController
{
    const AUTH_DIGEST = 'Digest';
    const AUTH_BASIC = 'Basic';

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
     * HTTP authentication realm.
     *
     * @var string
     */
    protected $authRealm;

    /**
     * WebDAV Public directory.
     *
     * @var string
     */
    protected $publicDir;

    /**
     * WebDAV Temporary directory.
     *
     * @var string
     */
    protected $tmpDir;

    /**
     * PDO Wrapped connection.
     *
     * @var \PDO
     */
    protected $pdo;

    /**
     * Base URI of the server.
     *
     * @var string
     */
    protected $baseUri;

    /**
     * Basic Auth Backend class.
     *
     * @var App\Services\BasicAuth
     */
    protected $basicAuthBackend;

    /**
     * Server.
     *
     * @var \Sabre\DAV\Server
     */
    protected $server;

    public function __construct(BasicAuth $basicAuthBackend, UrlGeneratorInterface $router, EntityManagerInterface $entityManager, bool $calDAVEnabled = true, bool $cardDAVEnabled = true, bool $webDAVEnabled = false, ?string $inviteAddress, ?string $authMethod, ?string $authRealm, ?string $publicDir, ?string $tmpDir)
    {
        $this->calDAVEnabled = $calDAVEnabled;
        $this->cardDAVEnabled = $cardDAVEnabled;
        $this->webDAVEnabled = $webDAVEnabled;
        $this->inviteAddress = $inviteAddress ?? null;

        $this->authMethod = $authMethod;
        $this->authRealm = $authRealm ?? User::DEFAULT_AUTH_REALM;

        $this->publicDir = $publicDir;
        $this->tmpDir = $tmpDir;

        $this->pdo = $entityManager->getConnection()->getWrappedConnection();
        $this->baseUri = $router->generate('dav', ['path' => '']);

        $this->basicAuthBackend = $basicAuthBackend;

        $this->initServer();
    }

    /**
     * @Route("/", name="home")
     */
    public function home()
    {
        return $this->render('index.html.twig');
    }

    private function initServer()
    {
        /*
         * The backends.
         */
        switch ($this->authMethod) {
            case self::AUTH_DIGEST:
                $authBackend = new \Sabre\DAV\Auth\Backend\PDO($this->pdo);
                break;
            case self::AUTH_BASIC:
            default:
                $authBackend = $this->basicAuthBackend;
                break;
        }

        $authBackend->setRealm($this->authRealm);

        $principalBackend = new \Sabre\DAVACL\PrincipalBackend\PDO($this->pdo);

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
            $calendarBackend = new \Sabre\CalDAV\Backend\PDO($this->pdo);
            $nodes[] = new \Sabre\CalDAV\CalendarRoot($principalBackend, $calendarBackend);
        }
        if ($this->cardDAVEnabled) {
            $carddavBackend = new \Sabre\CardDAV\Backend\PDO($this->pdo);
            $nodes[] = new \Sabre\CardDAV\AddressBookRoot($principalBackend, $carddavBackend);
        }
        if ($this->webDAVEnabled && $this->tmpDir && $this->publicDir) {
            $nodes[] = new \Sabre\DAV\FS\Directory($this->publicDir);
        }

        // The object tree needs in turn to be passed to the server class
        $this->server = new \Sabre\DAV\Server($nodes);
        $this->server->setBaseUri($this->baseUri);

        // Plugins
        $this->server->addPlugin(new \Sabre\DAV\Auth\Plugin($authBackend, $this->authRealm));
        $this->server->addPlugin(new \Sabre\DAV\Browser\Plugin());
        $this->server->addPlugin(new \Sabre\DAV\Sync\Plugin());
        $this->server->addPlugin(new \Sabre\DAVACL\Plugin());

        $this->server->addPlugin(new \Sabre\DAV\PropertyStorage\Plugin(
            new \Sabre\DAV\PropertyStorage\Backend\PDO($this->pdo)
        ));

        // CalDAV plugins
        if ($this->calDAVEnabled) {
            $this->server->addPlugin(new \Sabre\DAV\Sharing\Plugin());
            $this->server->addPlugin(new \Sabre\CalDAV\Plugin());
            $this->server->addPlugin(new \Sabre\CalDAV\Schedule\Plugin());
            $this->server->addPlugin(new \Sabre\CalDAV\SharingPlugin());
            $this->server->addPlugin(new \Sabre\CalDAV\ICSExportPlugin());
            if ($this->inviteAddress) {
                $this->server->addPlugin(new \Sabre\CalDAV\Schedule\IMipPlugin($this->inviteAddress));
            }
        }

        // CardDAV plugins
        if ($this->cardDAVEnabled) {
            $this->server->addPlugin(new \Sabre\CardDAV\Plugin());
            $this->server->addPlugin(new \Sabre\CardDAV\VCFExportPlugin());
        }

        // WebDAV plugins
        if ($this->webDAVEnabled && $this->tmpDir && $this->publicDir) {
            $lockBackend = new \Sabre\DAV\Locks\Backend\File($this->tmpDir.'/locksdb');
            $this->server->addPlugin(new \Sabre\DAV\Locks\Plugin($lockBackend));
            //$this->server->addPlugin(new \Sabre\DAV\Browser\GuessContentType()); // Waiting for https://github.com/sabre-io/dav/pull/1203
            $this->server->addPlugin(new \Sabre\DAV\TemporaryFileFilterPlugin($this->tmpDir));
        }
    }

    /**
     * @Route("/dav/{path}", name="dav", requirements={"path":".*"})
     */
    public function dav(Request $request, string $path)
    {
        $this->server->start();

        // Needed for Symfony, that expects a response otherwise
        exit;
    }
}
