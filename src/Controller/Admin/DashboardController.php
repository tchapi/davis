<?php

namespace App\Controller\Admin;

use App\Entity\AddressBook;
use App\Entity\CalendarInstance;
use App\Entity\CalendarObject;
use App\Entity\Card;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard')]
    public function dashboard(ManagerRegistry $doctrine): Response
    {
        $usersCount = $doctrine->getRepository(User::class)->count([]);
        $calendarsCount = $doctrine->getRepository(CalendarInstance::class)->count([]);
        $addressbooksCount = $doctrine->getRepository(AddressBook::class)->count([]);
        $eventsCount = $doctrine->getRepository(CalendarObject::class)->count([]);
        $contactsCount = $doctrine->getRepository(Card::class)->count([]);

        $timezoneParameter = $this->getParameter('timezone');

        return $this->render('dashboard.html.twig', [
            'users' => $usersCount,
            'calendars' => $calendarsCount,
            'addressbooks' => $addressbooksCount,
            'events' => $eventsCount,
            'contacts' => $contactsCount,
            'timezone' => [
                'actual_default' => date_default_timezone_get(),
                'not_set_in_app' => '' === $timezoneParameter,
                'bad_value' => '' !== $timezoneParameter && !in_array($timezoneParameter, \DateTimeZone::listIdentifiers()),
            ],
            'version' => \App\Version::VERSION,
            'sabredav_version' => \Sabre\DAV\Version::VERSION,
        ]);
    }
}
