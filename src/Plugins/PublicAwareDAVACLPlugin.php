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
    protected $public_calendar_enabled;

    public function __construct(EntityManagerInterface $entityManager, bool $public_enabled)
    {
        $this->em = $entityManager;
        $this->public_calendar_enabled = $public_enabled;
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
        $acl = parent::getAcl($node);

        if ($node instanceof \Sabre\CalDAV\Calendar) {
            if (CalendarInstance::ACCESS_PUBLIC === $node->getShareAccess()) {
                // We must add the ACL on the calendar itself
                $acl[] = [
                    'principal' => '{DAV:}unauthenticated',
                    'privilege' => '{DAV:}read',
                    'protected' => false,
                ];
            }
        } elseif ($node instanceof \Sabre\CalDAV\CalendarObject) {
            // The property is private in \Sabre\CalDAV\CalendarObject and we don't want to create
            // a new class just to access it, so we use a closure.
            $calendarInfo = (fn () => $this->calendarInfo)->call($node);
            // [0] is the calendarId, [1] is the calendarInstanceId
            $calendarInstanceId = $calendarInfo['id'][1];

            $calendar = $this->em->getRepository(CalendarInstance::class)->findOneById($calendarInstanceId);

            if ($calendar && $calendar->isPublic() && $this->public_calendar_enabled) {
                // We must add the ACL on the object itself
                $acl[] = [
                    'principal' => '{DAV:}unauthenticated',
                    'privilege' => '{DAV:}read',
                    'protected' => false,
                ];
            }
        }

        return $acl;
    }
}
