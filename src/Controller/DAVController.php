<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DAVController extends AbstractController
{
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

    public function __construct(bool $calDAVEnabled = true, bool $cardDAVEnabled = true, bool $webDAVEnabled = false, ?string $inviteAddress, ?string $authRealm, ?string $publicDir, ?string $tmpDir)
    {
        $this->calDAVEnabled = $calDAVEnabled;
        $this->cardDAVEnabled = $cardDAVEnabled;
        $this->webDAVEnabled = $webDAVEnabled;
        $this->inviteAddress = $inviteAddress ?? null;
        $this->authRealm = $authRealm ?? User::DEFAULT_AUTH_REALM;

        $this->publicDir = $publicDir;
        $this->tmpDir = $tmpDir;
    }

    /**
     * @Route("/", name="home")
     */
    public function home()
    {
        return $this->render('index.html.twig');
    }

    /**
     * @Route("/dav/{path}", name="dav", requirements={"path":".*"})
     */
    public function dav()
    {
        $pdo = $this->get('doctrine')->getEntityManager()->getConnection()->getWrappedConnection();

        /**
         * The backends.
         */
        $authBackend = new \Sabre\DAV\Auth\Backend\PDO($pdo);
        $authBackend->setRealm($this->authRealm);
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
        if ($this->webDAVEnabled && $this->tmpDir && $this->publicDir) {
            $nodes[] = new \Sabre\DAV\FS\Directory($this->publicDir);
        }

        // The object tree needs in turn to be passed to the server class
        $server = new \Sabre\DAV\Server($nodes);

        $route = $this->get('router')->generate('dav', ['path' => '']);
        $server->setBaseUri($route);

        // Plugins
        $server->addPlugin(new \Sabre\DAV\Auth\Plugin($authBackend, $this->authRealm));
        $server->addPlugin(new \Sabre\DAV\Browser\Plugin());
        $server->addPlugin(new \Sabre\DAV\Sync\Plugin());
        $server->addPlugin(new \Sabre\DAVACL\Plugin());

        $server->addPlugin(new \Sabre\DAV\PropertyStorage\Plugin(
            new \Sabre\DAV\PropertyStorage\Backend\PDO($pdo)
        ));

        // CalDAV plugins
        if ($this->calDAVEnabled) {
            $server->addPlugin(new \Sabre\DAV\Sharing\Plugin());
            $server->addPlugin(new \Sabre\CalDAV\Plugin());
            $server->addPlugin(new \Sabre\CalDAV\Schedule\Plugin());
            $server->addPlugin(new \Sabre\CalDAV\SharingPlugin());
            $server->addPlugin(new \Sabre\CalDAV\ICSExportPlugin());
            if ($this->inviteAddress) {
                $server->addPlugin(new \Sabre\CalDAV\Schedule\IMipPlugin($this->inviteAddress));
            }
        }

        // CardDAV plugins
        if ($this->cardDAVEnabled) {
            $server->addPlugin(new \Sabre\CardDAV\Plugin());
            $server->addPlugin(new \Sabre\CardDAV\VCFExportPlugin());
        }

        // WebDAV plugins
        if ($this->webDAVEnabled && $this->tmpDir && $this->publicDir) {
            $lockBackend = new \Sabre\DAV\Locks\Backend\File($this->tmpDir.'/locksdb');
            $server->addPlugin(new \Sabre\DAV\Locks\Plugin($lockBackend));
            //$server->addPlugin(new \Sabre\DAV\Browser\GuessContentType()); // Waiting for https://github.com/sabre-io/dav/pull/1203
            $server->addPlugin(new \Sabre\DAV\TemporaryFileFilterPlugin($this->tmpDir));
        }

        $server->start();

        // Needed for Symfony, that expects a response otherwise
        exit;
    }
}
