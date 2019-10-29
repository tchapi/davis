<?php

namespace App\Controller;

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

    public function __construct($calDAVEnabled = true, $cardDAVEnabled = true, ?string $inviteAddress, ?string $authRealm)
    {
        $this->calDAVEnabled = $calDAVEnabled;
        $this->cardDAVEnabled = $cardDAVEnabled;
        $this->inviteAddress = $inviteAddress ?? null;
        $this->authRealm = $authRealm ?? 'SabreDAV';
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

        // $pdo = new \PDO('sqlite:data/db.sqlite');
        // $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        /**
         * The backends. Yes we do really need all of them.
         *
         * This allows any developer to subclass just any of them and hook into their
         * own backend systems.
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

        $server->start();

        // Needed for Symfony, that expects a response otherwise
        exit;
    }
}
