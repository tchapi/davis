<?php

namespace App\Controller\Admin;

use App\Entity\AddressBook;
use App\Entity\CalendarInstance;
use App\Entity\CalendarObject;
use App\Entity\Card;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    /**
     * @Route("/dashboard", name="dashboard")
     */
    public function dashboard(ManagerRegistry $doctrine)
    {
        $users = $doctrine->getRepository(User::class)->findAll();
        $calendars = $doctrine->getRepository(CalendarInstance::class)->findAll();
        $addressbooks = $doctrine->getRepository(AddressBook::class)->findAll();
        $events = $doctrine->getRepository(CalendarObject::class)->findAll();
        $contacts = $doctrine->getRepository(Card::class)->findAll();

        return $this->render('dashboard.html.twig', [
            'users' => $users,
            'calendars' => $calendars,
            'addressbooks' => $addressbooks,
            'events' => $events,
            'contacts' => $contacts,
            'timezone' => date_default_timezone_get(),
            'version' => \App\Version::VERSION,
            'sabredav_version' => \Sabre\DAV\Version::VERSION,
        ]);
    }
}
