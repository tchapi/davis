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

    public function afterCardCreate(string $path, DAV\ICollection $parentNode): void
    {
        if (!$parentNode instanceof CardDAV\AddressBook) {
            return;
        }
        $this->handleCardChange($path, $parentNode);
    }

    public function afterCardUpdate(string $path, DAV\IFile $node): void
    {
        if (!$node instanceof CardDAV\ICard) {
            return;
        }
        $parentPath = dirname($path);
        $parentNode = $this->server->tree->getNodeForPath($parentPath);

        if (!$parentNode instanceof CardDAV\AddressBook) {
            return;
        }

        $this->handleCardChange($path, $parentNode);
    }

    public function beforeCardDelete(string $path): void
    {
        $node = $this->server->tree->getNodeForPath($path);

        if (!$node instanceof CardDAV\ICard) {
            return;
        }

        $parentPath = dirname($path);
        $parentNode = $this->server->tree->getNodeForPath($parentPath);

        if (!$parentNode instanceof CardDAV\AddressBook) {
            return;
        }

        $addressBookId = $parentNode->getProperties(['id'])['id'];

        $this->birthdayService->onCardDeleted($addressBookId, basename($path));
    }

    private function handleCardChange(string $path, CardDAV\AddressBook $parentNode): void
    {
        $cardUri = basename($path);
        $addressBookId = $parentNode->getProperties(['id'])['id'];
        $cardNode = $this->server->tree->getNodeForPath($path);

        $this->birthdayService->onCardChanged($addressBookId, $cardUri, $cardNode->get());
    }

    public function getPluginName(): string
    {
        return 'birthday-calendar';
    }
}
