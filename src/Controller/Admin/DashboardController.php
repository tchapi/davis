<?php

namespace App\Controller\Admin;

use App\Entity\AddressBook;
use App\Entity\CalendarInstance;
use App\Entity\CalendarObject;
use App\Entity\Card;
use App\Entity\User;
use App\Services\Diagnostics;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard')]
    public function dashboard(ManagerRegistry $doctrine, Diagnostics $diagnostics): Response
    {
        $usersCount = $doctrine->getRepository(User::class)->count([]);
        $calendarsCount = $doctrine->getRepository(CalendarInstance::class)->count([]);
        $addressBooksCount = $doctrine->getRepository(AddressBook::class)->count([]);
        $eventsCount = $doctrine->getRepository(CalendarObject::class)->count([]);
        $contactsCount = $doctrine->getRepository(Card::class)->count([]);

        return $this->render('dashboard.html.twig', [
            'usersCount' => $usersCount,
            'calendarsCount' => $calendarsCount,
            'addressBooksCount' => $addressBooksCount,
            'eventsCount' => $eventsCount,
            'contactsCount' => $contactsCount,
            'attentionCount' => $diagnostics->attentionCount(),
        ]);
    }
}
