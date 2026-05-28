<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Calendar;
use App\Entity\CalendarInstance;
use App\Entity\CalendarSubscription;
use Doctrine\Persistence\ManagerRegistry;
use Sabre\CalDAV\Backend\PDO as CalendarBackend;
use Sabre\DAV\Sharing\Plugin as SharingPlugin;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ICSCalendarsService
{
    private CalendarBackend $calendarBackend;

    public function __construct(
        private ManagerRegistry $doctrine,
        private HttpClientInterface $client,
        private bool $enabled,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setBackend(CalendarBackend $calendarBackend): void
    {
        $this->calendarBackend = $calendarBackend;
    }

    public function sync(CalendarSubscription $subscription): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $vcalendar = $this->retrieve($subscription->getSource());
        if ($vcalendar === null) {
            return;
        }

        $principalUri = $subscription->getPrincipalUri();
        $calendarUri = sha1($subscription->getSource()).'.ics';
        $calendarInstance = $this->doctrine->getRepository(CalendarInstance::class)->findOneBy(['principalUri' => $principalUri, 'uri' => $calendarUri]);

        if ($calendarInstance === null) {
            $em = $this->doctrine->getManager();

            $calendarInstance = $this->doctrine->getRepository(CalendarInstance::class)->findOneBy(['uri' => $calendarUri]);
            if ($calendarInstance === null) {
                $calendarComponents = [Calendar::COMPONENT_EVENTS, Calendar::COMPONENT_NOTES];
                if ($subscription->getStripTodos() === null) {
                    $calendarComponents[] = Calendar::COMPONENT_TODOS;
                }

                $calendar = new Calendar();
                $calendar->setComponents(implode(',', $calendarComponents));
                $em->persist($calendar);
            } else {
                $calendar = $calendarInstance->getCalendar();
            }

            $calendarInstance = (new CalendarInstance())
                ->setPrincipalUri($principalUri)
                ->setDisplayName($subscription->getDisplayName())
                ->setDescription('Calendar mirror for subscription '.$subscription->getDisplayName())
                ->setAccess(SharingPlugin::ACCESS_READ)
                ->setCalendarOrder($subscription->getCalendarOrder())
                ->setCalendarColor($subscription->getCalendarColor())
                ->setCalendar($calendar)
                ->setShareInviteStatus(SharingPlugin::INVITE_ACCEPTED)
                ->setUri($calendarUri);
            $em->persist($calendarInstance);
            $em->flush();
        } else {
            $calendar = $calendarInstance->getCalendar();
        }

        $existingUris = [];
        foreach ($calendar->getObjects() as $object) {
            $existingUris[] = $object->getUri();
        }

        $elements = ['VEVENT', 'VJOURNAL'];
        if ($subscription->getStripTodos() === null) {
            $elements[] = 'VTODO';
        }

        $seenUris = [];
        $backendId = [$calendar->getId(), $calendarInstance->getId()];
        foreach ($elements as $element) {
            foreach ($vcalendar->select($element) as $event) {
                $uid = $event->UID->getValue();
                if ($uid === null) {
                    continue;
                }

                // Stable URI derived from UID
                $objectUri = sha1($uid).'.ics';
                $seenUris[] = $objectUri;

                if ($subscription->getStripAlarms() !== null) {
                    $event->remove('VALARM');
                }

                if ($subscription->getStripAttachments() !== null) {
                    $event->remove('ATTACH');
                }

                $eventCalendar = new VCalendar();
                $eventCalendar->add($event);

                if (in_array($objectUri, $existingUris, true)) {
                    $this->calendarBackend->updateCalendarObject(
                        $backendId,
                        $objectUri,
                        $eventCalendar->serialize()
                    );
                } else {
                    $this->calendarBackend->createCalendarObject(
                        $backendId,
                        $objectUri,
                        $eventCalendar->serialize()
                    );
                }
            }
        }

        foreach ($existingUris as $uri) {
            if (!in_array($uri, $seenUris, true)) {
                $this->calendarBackend->deleteCalendarObject(
                    $backendId,
                    $uri
                );
            }
        }
    }

    public function onSubscriptionCreate(int $subscriptionId): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $subscription = $this->doctrine->getRepository(CalendarSubscription::class)->findOneById($subscriptionId);
        $this->sync($subscription);
    }

    public function onSubscriptionDelete(int $subscriptionId): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $subscription = $this->doctrine->getRepository(CalendarSubscription::class)->findOneById($subscriptionId);

        $principalUri = $subscription->getPrincipalUri();
        $calendarUri = sha1($subscription->getSource()).'.ics';
        $calendarInstance = $this->doctrine->getRepository(CalendarInstance::class)->findOneBy(['principalUri' => $principalUri, 'uri' => $calendarUri]);
        if ($calendarInstance === null) {
            return;
        }

        $em = $this->doctrine->getManager();

        $em->remove($calendarInstance);

        $calendar = $calendarInstance->getCalendar();
        $calendar->getInstances()->removeElement($calendarInstance);
        if ($calendar->getInstances()->isEmpty()) {
            foreach ($calendar->getObjects() as $object) {
                $em->remove($object);
            }
            foreach ($calendar->getChanges() as $change) {
                $em->remove($change);
            }

            $em->remove($calendar);
        }

        $em->flush();
    }

    private function retrieve(string $url): ?VCalendar
    {
        $response = $this->client->request('GET', $url);
        if ($response->getStatusCode() !== 200) {
            return null;
        }

        try {
            $vcal = Reader::read($response->getContent());
            if ($vcal instanceof VCalendar) {
                return $vcal;
            }
        } catch (\Exception $e) { }

        return null;
    }
}
