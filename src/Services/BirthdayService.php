<?php

declare(strict_types=1);

/**
 * Largely inspired by https://github.com/nextcloud/server/blob/master/apps/dav/lib/CalDAV/BirthdayService.php which is licensed in these terms:
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Services;

use App\Constants;
use App\Entity\AddressBook;
use App\Entity\Calendar;
use App\Entity\CalendarInstance;
use App\Entity\CalendarObject;
use App\Entity\Card;
use App\Entity\Principal;
use Doctrine\Persistence\ManagerRegistry;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\DateTimeParser;
use Sabre\VObject\Document;
use Sabre\VObject\InvalidDataException;
use Sabre\VObject\Property\VCard\DateAndOrTime;
use Sabre\VObject\Reader;

class BirthdayService
{
    public function __construct(
        private ManagerRegistry $doctrine,
        private string $birthdayReminderOffset,
    ) {
    }

    public function onCardChanged(int $addressBookId, string $cardUri, string $cardData): void
    {
        $book = $this->doctrine->getRepository(AddressBook::class)->findOneById($addressBookId);

        if (!$book->isIncludedInBirthdayCalendar()) {
            return;
        }

        $principalUri = $book->getPrincipalUri();
        $calendar = $this->ensureBirthdayCalendarExists($principalUri);

        $this->updateCalendar($cardUri, $cardData, $book, $calendar->getCalendar());
    }

    public function onCardDeleted(int $addressBookId, string $cardUri): void
    {
        $book = $this->doctrine->getRepository(AddressBook::class)->findOneById($addressBookId);

        if (!$book->isIncludedInBirthdayCalendar()) {
            return;
        }

        $principalUri = $book->getPrincipalUri();
        $calendar = $this->ensureBirthdayCalendarExists($principalUri);

        $objectUri = $book->getUri().'-'.$cardUri.'.ics';
        $calendarObject = $this->doctrine->getRepository(CalendarObject::class)->findOneBy(['calendar' => $calendar, 'uri' => $objectUri]);

        $em = $this->doctrine->getManager();
        $em->remove($calendarObject);
        $em->flush();
    }

    public function shouldBirthdayCalendarExist(string $principalUri): bool
    {
        $addressbooks = $this->doctrine->getRepository(AddressBook::class)->findByPrincipalUri($principalUri);

        return array_reduce($addressbooks, function ($carry, $addressbook) {
            return $carry || $addressbook->isIncludedInBirthdayCalendar();
        }, false);
    }

    public function ensureBirthdayCalendarExists(string $principalUri): CalendarInstance
    {
        $instance = $this->doctrine->getRepository(CalendarInstance::class)->findOneBy(['principalUri' => $principalUri, 'uri' => Constants::BIRTHDAY_CALENDAR_URI]);

        if ($instance) {
            return $instance;
        }

        $em = $this->doctrine->getManager();

        $calendar = new Calendar();
        $em->persist($calendar);

        $instance = (new CalendarInstance())
                    ->setPrincipalUri($principalUri)
                    ->setDisplayName('🎁 Birthdays')
                    ->setDescription('Birthdays')
                    ->setAccess(CalendarInstance::ACCESS_READ)
                    ->setCalendarOrder(0)
                    ->setCalendar($calendar)
                    ->setTransparent(1)
                    ->setShareInviteStatus(CalendarInstance::INVITE_ACCEPTED)
                    ->setUri(Constants::BIRTHDAY_CALENDAR_URI);

        $em->persist($instance);
        $em->flush();

        return $instance;
    }

    public function deleteBirthdayCalendar(string $principalUri): void
    {
        $instance = $this->doctrine->getRepository(CalendarInstance::class)->findOneBy(['principalUri' => $principalUri, 'uri' => Constants::BIRTHDAY_CALENDAR_URI]);

        if (!$instance) {
            return;
        }

        $em = $this->doctrine->getManager();

        $em->remove($instance);
        $em->remove($instance->getCalendar());
        $em->flush();
    }

    /**
     * @throws InvalidDataException
     */
    public function buildDataFromContact(string $cardData): ?VCalendar
    {
        if (empty($cardData)) {
            return null;
        }

        try {
            $doc = Reader::read($cardData);
            // We're always converting to vCard 4.0 so we can rely on the
            // VCardConverter handling the X-APPLE-OMIT-YEAR property for us.
            if (!$doc instanceof VCard) {
                return null;
            }
            $doc = $doc->convert(Document::VCARD40);
        } catch (\Exception $e) {
            return null;
        }

        if (!isset($doc->BDAY) || !isset($doc->FN)) {
            return null;
        }

        $birthday = $doc->BDAY;
        if (!(string) $birthday) {
            return null;
        }

        // Skip if the BDAY property is not of the right type.
        if (!$birthday instanceof DateAndOrTime) {
            return null;
        }

        // Skip if we can't parse the BDAY value.
        try {
            $dateParts = DateTimeParser::parseVCardDateTime($birthday->getValue());
        } catch (InvalidDataException $e) {
            return null;
        }

        if (null !== $dateParts['year']) {
            $parameters = $birthday->parameters();
            $omitYear = (isset($parameters['X-APPLE-OMIT-YEAR']) && $parameters['X-APPLE-OMIT-YEAR'] === $dateParts['year']);
            // 'X-APPLE-OMIT-YEAR' is not always present, at least iOS 12.4 uses the hard coded date of 1604 (the start of the gregorian calendar) when the year is unknown
            if ($omitYear || 1604 === (int) $dateParts['year']) {
                $dateParts['year'] = null;
            }
        }

        $originalYear = null;
        if (null !== $dateParts['year']) {
            $originalYear = (int) $dateParts['year'];
        }

        try {
            if ($birthday instanceof DateAndOrTime) {
                $date = $birthday->getDateTime();
            } else {
                $date = new \DateTimeImmutable($birthday);
            }
        } catch (\Exception $e) {
            return null;
        }

        $summary = '🎂 '.$doc->FN->getValue().($originalYear ? (' ('.$originalYear.')') : '');

        $vCal = new VCalendar();
        $vCal->VERSION = '2.0';
        $vCal->PRODID = '-//IDN davis//Birthday calendar//EN';
        $vEvent = $vCal->createComponent('VEVENT');
        $vEvent->add('DTSTART');
        $vEvent->DTSTART->setDateTime(
            $date
        );
        $vEvent->DTSTART['VALUE'] = 'DATE';
        $vEvent->add('DTEND');

        $dtEndDate = (new \DateTime())->setTimestamp($date->getTimeStamp());
        $dtEndDate->add(new \DateInterval('P1D'));
        $vEvent->DTEND->setDateTime(
            $dtEndDate
        );

        $vEvent->DTEND['VALUE'] = 'DATE';
        $vEvent->{'UID'} = $doc->UID;

        $leapDay = (2 === (int) $dateParts['month']
                && 29 === (int) $dateParts['date']);
        if (null === $dateParts['year'] || $originalYear < 1970) {
            $birthday = ($leapDay ? '1972-' : '1970-')
                .$dateParts['month'].'-'.$dateParts['date'];
        }

        if ($leapDay) {
            /* Sabre\VObject supports BYMONTHDAY only if BYMONTH
             * is also set */
            $vEvent->{'RRULE'} = 'FREQ=YEARLY;BYMONTH=2;BYMONTHDAY=-1';
        } else {
            $vEvent->{'RRULE'} = 'FREQ=YEARLY';
        }

        $vEvent->{'SUMMARY'} = $summary;
        $vEvent->{'TRANSP'} = 'TRANSPARENT';

        // Set a reminder, if needed
        if ('false' !== strtolower($this->birthdayReminderOffset)) {
            $alarm = $vCal->createComponent('VALARM');
            $alarm->add($vCal->createProperty('TRIGGER', $this->birthdayReminderOffset, ['VALUE' => 'DURATION']));
            $alarm->add($vCal->createProperty('ACTION', 'DISPLAY'));
            $alarm->add($vCal->createProperty('DESCRIPTION', $vEvent->{'SUMMARY'}));
            $vEvent->add($alarm);
        }

        $vCal->add($vEvent);

        return $vCal;
    }

    public function resetForPrincipal(string $principal): void
    {
        $calendarInstance = $this->doctrine->getRepository(CalendarInstance::class)->findOneBy(['principalUri' => $principal, 'uri' => Constants::BIRTHDAY_CALENDAR_URI]);

        if (!$calendarInstance) {
            return; // The user's birthday calendar doesn't exist, no need to purge it
        }

        $calendarObjects = $this->doctrine->getRepository(CalendarObject::class)->findByCalendar($calendarInstance->getCalendar());
        $em = $this->doctrine->getManager();

        foreach ($calendarObjects as $calendarObject) {
            $em->remove($calendarObject);
        }

        $em->flush();
    }

    public function syncUser(string $username): void
    {
        $this->syncPrincipal(Principal::PREFIX.$username);
    }

    public function syncPrincipal(string $principal): void
    {
        if (!$this->shouldBirthdayCalendarExist($principal)) {
            $this->deleteBirthdayCalendar($principal);

            return;
        }

        $calendarInstance = $this->ensureBirthdayCalendarExists($principal);

        // Reset the calendar
        $this->resetForPrincipal($principal);

        // Get all address books that should be included and iterate
        $addressbooks = $this->doctrine->getRepository(AddressBook::class)->findBy(['principalUri' => $principal, 'includedInBirthdayCalendar' => true]);
        foreach ($addressbooks as $book) {
            $cards = $this->doctrine->getRepository(Card::class)->findByAddressBook($book);

            foreach ($cards as $card) {
                $this->onCardChanged($book->getId(), $card->getUri(), $card->getCardData());
            }
        }
    }

    public function birthdayEvenChanged(string $existingCalendarData, VCalendar $newCalendarData): bool
    {
        try {
            $existingBirthday = Reader::read($existingCalendarData);
        } catch (\Exception $ex) {
            return true;
        }

        return
            $newCalendarData->VEVENT->DTSTART->getValue() !== $existingBirthday->VEVENT->DTSTART->getValue()
            || $newCalendarData->VEVENT->SUMMARY->getValue() !== $existingBirthday->VEVENT->SUMMARY->getValue()
        ;
    }

    /**
     * @throws InvalidDataException
     */
    private function updateCalendar(string $cardUri, string $cardData, AddressBook $book, Calendar $calendar): void
    {
        $objectUid = $book->getUri().'-'.$cardUri;
        $objectUri = $objectUid.'.ics';
        $calendarData = $this->buildDataFromContact($cardData);

        $existing = $this->doctrine->getRepository(CalendarObject::class)->findOneBy(['calendar' => $calendar, 'uri' => $objectUri]);

        $em = $this->doctrine->getManager();

        if (null === $calendarData) {
            if (null !== $existing) {
                $em->remove($existing);
            }
        } else {
            $serializedCalendarData = $calendarData->serialize();
            $vEvent = $calendarData->getComponents()[0];
            $maxDate = new \DateTime(Constants::MAX_DATE);

            if (null === $existing) {
                $calendarObject = (new CalendarObject())
                            ->setCalendar($calendar)
                            ->setUri($objectUri)
                            ->setComponentType('VEVENT')
                            ->setUid($objectUid)
                            ->setLastModified((new \DateTime())->getTimestamp())
                            ->setFirstOccurence($vEvent->DTSTART->getDateTime()->getTimeStamp())
                            ->setLastOccurence($maxDate->getTimestamp())
                            ->setEtag(md5($serializedCalendarData))
                            ->setSize(strlen($serializedCalendarData))
                            ->setCalendarData($serializedCalendarData);

                $em->persist($calendarObject);
            } else {
                if ($this->birthdayEvenChanged($existing->getCalendarData(), $calendarData)) {
                    $existing
                        ->setLastModified((new \DateTime())->getTimestamp())
                        ->setFirstOccurence($vEvent->DTSTART->getDateTime()->getTimeStamp())
                        ->setLastOccurence($maxDate->getTimestamp())
                        ->setEtag(md5($serializedCalendarData))
                        ->setSize(strlen($serializedCalendarData))
                        ->setCalendarData($serializedCalendarData);
                }
            }
        }

        $em->flush();
    }
}
