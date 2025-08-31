<?php

namespace App\Plugins;

use App\Services\BirthdayService;
use Sabre\CardDAV;
use Sabre\DAV;

class BirthdayCalendarPlugin extends DAV\ServerPlugin
{
    /**
     * @var BirthdayService
     */
    protected $birthdayService;

    /**
     * @var DAV\Server
     */
    protected $server;

    public function __construct(BirthdayService $birthdayService)
    {
        $this->birthdayService = $birthdayService;
    }

    public function initialize(DAV\Server $server)
    {
        $this->server = $server;

        // Hook into card creation
        $server->on('afterCreateFile', [$this, 'afterCardCreate']);

        // Hook into card updates
        $server->on('afterWriteContent', [$this, 'afterCardUpdate']);

        // Hook into card deletion
        // Note: The node no longer exists at afterCardDelete so we
        // use beforeCardDelete for simplicity
        $server->on('beforeUnbind', [$this, 'beforeCardDelete']);
    }

    private function resyncCurrentPrincipal()
    {
        $authPlugin = $this->server->getPlugin('auth');

        if (!$authPlugin) {
            return null;
        }

        $principal = $authPlugin->getCurrentPrincipal();

        if ($principal) {
            $this->birthdayService->syncPrincipal($principal);
        }
    }

    public function afterCardCreate($path, DAV\ICollection $parentNode)
    {
        if (!$parentNode instanceof CardDAV\IAddressBook) {
            return;
        }

        $principal = $this->resyncCurrentPrincipal();
    }

    public function afterCardUpdate($path, DAV\IFile $node)
    {
        if (!$node instanceof CardDAV\ICard) {
            return;
        }

        $principal = $this->resyncCurrentPrincipal();
    }

    public function beforeCardDelete($path)
    {
        $node = $this->server->tree->getNodeForPath($path);

        if (!$node instanceof CardDAV\ICard) {
            return;
        }

        $principal = $this->resyncCurrentPrincipal();
    }

    public function getPluginName(): string
    {
        return 'birthday-calendar';
    }
}
