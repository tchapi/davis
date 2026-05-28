<?php

declare(strict_types=1);

namespace App\Plugins;

use App\Services\ICSCalendarsService;
use Sabre\CalDAV\Backend\PDO as CalendarBackend;
use Sabre\CalDAV\Subscriptions\ISubscription;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;

class ICSCalendarsPlugin extends ServerPlugin
{
    private ICSCalendarsService $icsService;
    private Server $server;

    public function __construct(ICSCalendarsService $icsService, CalendarBackend $calendarBackend)
    {
        $this->icsService = $icsService;
        $this->icsService->setBackend($calendarBackend);
    }

    public function initialize(Server $server)
    {
        $this->server = $server;

        // Hook into creation
        $server->on('afterBind', [$this, 'afterSubscriptionCreate']);

        // Hook into deletion
        // Note: The node no longer exists after unbind so we hook in before
        $server->on('beforeUnbind', [$this, 'beforeSubscriptionDelete']);
    }

    public function afterSubscriptionCreate(string $path): void
    {
        $node = $this->server->tree->getNodeForPath($path);
        if (!$node instanceof ISubscription) {
            return;
        }

        $this->icsService->onSubscriptionCreate($node->getProperties(['id'])['id']);
    }

    public function beforeSubscriptionDelete(string $path): void
    {
        $node = $this->server->tree->getNodeForPath($path);
        if (!$node instanceof ISubscription) {
            return;
        }

        $this->icsService->onSubscriptionDelete($node->getProperties(['id'])['id']);
    }

    public function getPluginInfo()
    {
        return [
            'name' => $this->getPluginName(),
            'description' => 'Creates calendars for Subscriptions.',
            'link' => 'https://github.com/tchapi/davis',
        ];
    }
}
