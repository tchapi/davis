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

    /**
     * Whether a calendar instance is public, by instance id.
     *
     * getAcl() runs once per node, so a PROPFIND over a calendar asks the same question for the
     * collection and for every object in it. The plugin is built per request, so this lasts no
     * longer than one.
     *
     * @var array<int, bool>
     */
    private $isPublicByInstanceId = [];

    public function __construct(EntityManagerInterface $entityManager, bool $public_calendars_enabled)
    {
        $this->em = $entityManager;
        $this->public_calendars_enabled = $public_calendars_enabled;
    }

    /**
     * We override this method so that public objects can be seen correctly in the browser,
     * with the assets (css, images).
     *
     * The browser plugin always builds asset URLs against the server base URI
     * (see \Sabre\DAV\Browser\Plugin::getAssetUrl), i.e. `GET /dav/?sabreAction=asset&assetName=...`.
     * The root collection is only readable by authenticated principals, so we skip the ACL
     * check for that exact case only. Every other method or path (e.g. a GET or PUT on a
     * calendar object with `?sabreAction=asset` appended) must go through the regular checks:
     * this method is the only place where `{DAV:}read` and `{DAV:}write-content` are enforced.
     */
    public function beforeMethod(RequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getQueryParameters();
        if (isset($params['sabreAction']) && 'asset' === $params['sabreAction']
            && in_array($request->getMethod(), ['GET', 'HEAD'], true)
            && '' === $request->getPath()) {
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
                $calendarInstanceId = $calendarInfo['id'][1];

                if (!\array_key_exists($calendarInstanceId, $this->isPublicByInstanceId)) {
                    $calendar = $this->em->getRepository(CalendarInstance::class)->find($calendarInstanceId);
                    $this->isPublicByInstanceId[$calendarInstanceId] = (bool) $calendar?->isPublic();
                }

                if ($this->isPublicByInstanceId[$calendarInstanceId]) {
                    // `{DAV:}all` covers authenticated and unauthenticated alike:
                    // `{DAV:}unauthenticated` would match logged-out requests only.
                    $acl[] = [
                        'principal' => '{DAV:}all',
                        'privilege' => '{DAV:}read',
                        'protected' => false,
                    ];
                }
            }
        }

        return $acl;
    }
}
