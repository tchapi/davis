<?php

namespace App\Plugins;

use App\Entity\CalendarInstance;
use Doctrine\ORM\EntityManagerInterface;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class PublicAwareDAVACLPlugin extends \Sabre\DAVACL\Plugin
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var bool
     */
    protected $public_calendars_enabled;

    public function __construct(EntityManagerInterface $entityManager, bool $public_calendars_enabled)
    {
        $this->em = $entityManager;
        $this->public_calendars_enabled = $public_calendars_enabled;
    }

    /**
     * We override this method so that public objects can be seen correctly in the browser,
     * with the assets (css, images).
     */
    public function beforeMethod(RequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getQueryParameters();
        if (isset($params['sabreAction']) && 'asset' === $params['sabreAction']) {
            return;
        }

        return parent::beforeMethod($request, $response);
    }

    public function getAcl($node): array
    {
        // Note:
        // '{DAV:}unauthenticated' - only unauthenticated users
        // '{DAV:}all' - all users (both authenticated and unauthenticated)
        // '{DAV:}authenticated' - only authenticated users
        $acl = parent::getAcl($node);

        if ($this->public_calendars_enabled) {
            // Handle both Calendar AND SharedCalendar (which extends Calendar)
            if ($node instanceof \Sabre\CalDAV\Calendar || $node instanceof \Sabre\CalDAV\CalendarObject) {
                // The property is private in \Sabre\CalDAV\CalendarObject and we don't want to create
                // a new class just to access it, so we use a closure.
                $calendarInfo = (fn () => $this->calendarInfo)->call($node);
                // [0] is the calendarId, [1] is the calendarInstanceId
                if (isset($calendarInfo['id']) && is_array($calendarInfo['id']) && isset($calendarInfo['id'][1])) {
                    $calendarInstanceId = $calendarInfo['id'][1];

                    $calendar = $this->em->getRepository(CalendarInstance::class)->findOneById($calendarInstanceId);

                    if ($calendar && $calendar->isPublic()) {
                        // Add unauthenticated read access on the object itself
                        $acl[] = [
                            'principal' => '{DAV:}unauthenticated',
                            'privilege' => '{DAV:}read',
                            'protected' => false,
                        ];
                    }
                }
            }
        }

        return $acl;
    }
}
