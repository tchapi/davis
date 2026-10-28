<?php

namespace App\Plugins;

use Sabre\CalDAV\Plugin as CalDAVPlugin;
use Sabre\CalDAV\Schedule\IInbox;
use Sabre\DAV;
use Sabre\DAV\INode;
use Sabre\DAV\PropFind;

/**
 * macOS Calendar errors out ("There was a problem accessing the availability for this
 * account", issue #317) when the inbox has no `calendar-availability`, which is the
 * case for every user until a client writes one. We serve a default (weekdays, 09:00
 * to 17:00) to PROPFIND requests until then; a stored value always wins.
 *
 * Free-busy lookups (made while answering a scheduling POST) don't get the default, so
 * users who never saved an availability stay always available, as they always were.
 */
final class DefaultAvailabilityPlugin extends DAV\ServerPlugin
{
    public const AVAILABILITY_PROPERTY = '{'.CalDAVPlugin::NS_CALDAV.'}calendar-availability';

    /**
     * Shaped like what macOS Calendar writes, the only shape it can display: a weekly
     * rule and a slot ending on the day it starts. Floating times, so every client reads
     * 09:00 to 17:00 in its own time zone.
     */
    public const DEFAULT_AVAILABILITY = "BEGIN:VCALENDAR\r\n".
        "VERSION:2.0\r\n".
        "PRODID:-//tchapi//Davis//EN\r\n".
        "BEGIN:VAVAILABILITY\r\n".
        "UID:davis-default-availability\r\n".
        "DTSTAMP:20000103T000000Z\r\n".
        "BEGIN:AVAILABLE\r\n".
        "UID:davis-default-available\r\n".
        "DTSTAMP:20000103T000000Z\r\n".
        "DTSTART:20000103T090000\r\n".
        "DTEND:20000103T170000\r\n".
        "RRULE:FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR\r\n".
        "END:AVAILABLE\r\n".
        "END:VAVAILABILITY\r\n".
        "END:VCALENDAR\r\n";

    private DAV\Server $server;

    public function initialize(DAV\Server $server): void
    {
        $this->server = $server;

        // After the PropertyStorage plugin (130), so a stored value is already there
        $server->on('propFind', [$this, 'propFind'], 150);
    }

    public function propFind(PropFind $propFind, INode $node): void
    {
        if ($node instanceof IInbox && 'PROPFIND' === $this->server->httpRequest->getMethod()) {
            // Only fills the property if it was requested and is still missing
            $propFind->handle(self::AVAILABILITY_PROPERTY, self::DEFAULT_AVAILABILITY);
        }
    }
}
