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
        $users = $doctrine->getRepository(User::class)->findAll();
        $calendars = $doctrine->getRepository(CalendarInstance::class)->findAll();
        $addressbooks = $doctrine->getRepository(AddressBook::class)->findAll();
        $events = $doctrine->getRepository(CalendarObject::class)->findAll();
        $contacts = $doctrine->getRepository(Card::class)->findAll();

        $timezoneParameter = $this->getParameter('timezone');

        return $this->render('dashboard.html.twig', [
            'users' => $users,
            'calendars' => $calendars,
            'addressbooks' => $addressbooks,
            'events' => $events,
            'contacts' => $contacts,
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
