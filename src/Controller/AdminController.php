<?php

namespace App\Controller;

use App\Entity\AddressBook;
use App\Entity\Calendar;
use App\Entity\CalendarInstance;
use App\Entity\CalendarObject;
use App\Entity\Card;
use App\Entity\Principal;
use App\Entity\User;
use App\Form\UserType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class AdminController extends AbstractController
{
    /**
     * @Route("/dashboard", name="dashboard")
     */
    public function dashboard()
    {
        $users = $this->get('doctrine')->getRepository(User::class)->findAll();
        $calendars = $this->get('doctrine')->getRepository(Calendar::class)->findAll();
        $addressbooks = $this->get('doctrine')->getRepository(AddressBook::class)->findAll();
        $events = $this->get('doctrine')->getRepository(CalendarObject::class)->findAll();
        $contacts = $this->get('doctrine')->getRepository(Card::class)->findAll();

        return $this->render('dashboard.html.twig', [
            'users' => $users,
            'calendars' => $calendars,
            'addressbooks' => $addressbooks,
        ]);
    }

    /**
     * @Route("/users", name="users")
     */
    public function users()
    {
        $principals = $this->get('doctrine')->getRepository(Principal::class)->findAll();

        return $this->render('users/index.html.twig', [
            'principals' => $principals,
        ]);
    }

    /**
     * @Route("/users/new", name="user_create")
     * @Route("/users/edit/{username}", name="user_edit")
     */
    public function userCreate(?string $username)
    {
        if ($username) {
            $user = $this->get('doctrine')->getRepository(User::class)->findOneByUsername($username);
            if (!$user) {
                throw new \Exception('User not found');
            }
        } else {
            $user = new User();
        }

        $form = $this->createForm(UserType::class, $user);

        return $this->render('users/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/users/delete/{username}", name="user_delete")
     */
    public function userDelete(?string $username)
    {
        //TODO
    }

    /**
     * @Route("/calendars/{username}", name="calendars")
     */
    public function calendars(string $username)
    {
        $principal = $this->get('doctrine')->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username);
        $calendars = $this->get('doctrine')->getRepository(CalendarInstance::class)->findByPrincipalUri(Principal::PREFIX.$username);

        return $this->render('calendars/index.html.twig', [
            'calendars' => $calendars,
            'principal' => $principal,
        ]);
    }

    /**
     * @Route("/adressbooks/{username}", name="address_books")
     */
    public function addressBooks(string $username)
    {
        $principal = $this->get('doctrine')->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username);
        $addressbooks = $this->get('doctrine')->getRepository(AddressBook::class)->findByPrincipalUri(Principal::PREFIX.$username);

        return $this->render('addressbooks/index.html.twig', [
            'addressbooks' => $addressbooks,
            'principal' => $principal,
        ]);
    }
}
