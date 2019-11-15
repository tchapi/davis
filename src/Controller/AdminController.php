<?php

namespace App\Controller;

use App\Entity\AddressBook;
use App\Entity\Calendar;
use App\Entity\CalendarInstance;
use App\Entity\CalendarObject;
use App\Entity\CalendarSubscription;
use App\Entity\Card;
use App\Entity\Principal;
use App\Entity\SchedulingObject;
use App\Entity\User;
use App\Form\AddressBookType;
use App\Form\CalendarInstanceType;
use App\Form\UserType;
use App\Services\Utils;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatorInterface;

class AdminController extends AbstractController
{
    /**
     * @Route("/dashboard", name="dashboard")
     */
    public function dashboard()
    {
        $users = $this->get('doctrine')->getRepository(User::class)->findAll();
        $calendars = $this->get('doctrine')->getRepository(CalendarInstance::class)->findAll();
        $addressbooks = $this->get('doctrine')->getRepository(AddressBook::class)->findAll();
        $events = $this->get('doctrine')->getRepository(CalendarObject::class)->findAll();
        $contacts = $this->get('doctrine')->getRepository(Card::class)->findAll();

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

    /**
     * @Route("/users", name="users")
     */
    public function users()
    {
        $principals = $this->get('doctrine')->getRepository(Principal::class)->findByIsMain(true);

        return $this->render('users/index.html.twig', [
            'principals' => $principals,
        ]);
    }

    /**
     * @Route("/users/new", name="user_create")
     * @Route("/users/edit/{username}", name="user_edit")
     */
    public function userCreate(Utils $utils, Request $request, ?string $username, TranslatorInterface $trans)
    {
        if ($username) {
            $user = $this->get('doctrine')->getRepository(User::class)->findOneByUsername($username);
            if (!$user) {
                throw $this->createNotFoundException('User not found');
            }
            $oldHash = $user->getPassword();
            $principal = $this->get('doctrine')->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username);
        } else {
            $user = new User();
            $principal = new Principal();
        }

        $form = $this->createForm(UserType::class, $user, ['new' => !$username]);

        $form->get('displayName')->setData($principal->getDisplayName());
        $form->get('email')->setData($principal->getEmail());

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $displayName = $form->get('displayName')->getData();
            $email = $form->get('email')->getData();

            // Create password for user
            if ($username && is_null($user->getPassword())) {
                // The user is not new and does not want to change its password
                $user->setPassword($oldHash);
            } else {
                $hash = $utils->hashPassword($user->getUsername(), $user->getPassword());
                $user->setPassword($hash);
            }

            $entityManager = $this->get('doctrine')->getManager();

            // If it's a new user, create default calendar and address book, and principal
            if (null === $user->getId()) {
                $principal->setUri(Principal::PREFIX.$user->getUsername());

                $calendarInstance = new CalendarInstance();
                $calendar = new Calendar();
                $calendarInstance->setPrincipalUri(Principal::PREFIX.$user->getUsername())
                         ->setUri('default') // No risk of collision since unicity is guaranteed by the new user principal
                         ->setDisplayName($trans->trans('default.calendar.title'))
                         ->setDescription($trans->trans('default.calendar.description', ['user' => $displayName]))
                         ->setCalendar($calendar);

                $addressbook = new AddressBook();
                $addressbook->setPrincipalUri(Principal::PREFIX.$user->getUsername())
                         ->setUri('default') // No risk of collision since unicity is guaranteed by the new user principal
                         ->setDisplayName($trans->trans('default.addressbook.title'))
                         ->setDescription($trans->trans('default.addressbook.description', ['user' => $displayName]));
                $entityManager->persist($calendarInstance);
                $entityManager->persist($addressbook);
                $entityManager->persist($principal);
            }

            $principal->setDisplayName($displayName)
                      ->setEmail($email);

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', $trans->trans('user.saved'));

            return $this->redirectToRoute('users');
        }

        return $this->render('users/edit.html.twig', [
            'form' => $form->createView(),
            'username' => $username,
        ]);
    }

    /**
     * @Route("/users/delete/{username}", name="user_delete")
     */
    public function userDelete(string $username, TranslatorInterface $trans)
    {
        $user = $this->get('doctrine')->getRepository(User::class)->findOneByUsername($username);
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        $entityManager = $this->get('doctrine')->getManager();
        $entityManager->remove($user);

        $principal = $this->get('doctrine')->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username);
        $entityManager->remove($principal);

        // Remove calendars and addressbooks
        $calendars = $this->get('doctrine')->getRepository(CalendarInstance::class)->findByPrincipalUri(Principal::PREFIX.$username);
        foreach ($calendars ?? [] as $instance) {
            foreach ($instance->getCalendar()->getObjects() ?? [] as $object) {
                $entityManager->remove($object);
            }
            foreach ($instance->getCalendar()->getChanges() ?? [] as $change) {
                $entityManager->remove($change);
            }
            $entityManager->remove($instance->getCalendar());
            $entityManager->remove($instance);
        }
        $calendarsSubscriptions = $this->get('doctrine')->getRepository(CalendarSubscription::class)->findByPrincipalUri(Principal::PREFIX.$username);
        foreach ($calendarsSubscriptions ?? [] as $subscription) {
            $entityManager->remove($subscription);
        }
        $schedulingObjects = $this->get('doctrine')->getRepository(SchedulingObject::class)->findByPrincipalUri(Principal::PREFIX.$username);
        foreach ($schedulingObjects ?? [] as $object) {
            $entityManager->remove($object);
        }

        $addressbooks = $this->get('doctrine')->getRepository(AddressBook::class)->findByPrincipalUri(Principal::PREFIX.$username);
        foreach ($addressbooks ?? [] as $addressbook) {
            foreach ($addressbook->getCards() ?? [] as $card) {
                $entityManager->remove($card);
            }
            foreach ($addressbook->getChanges() ?? [] as $change) {
                $entityManager->remove($change);
            }
            $entityManager->remove($addressbook);
        }

        $entityManager->flush();
        $this->addFlash('success', $trans->trans('user.deleted'));

        return $this->redirectToRoute('users');
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
            'username' => $username,
        ]);
    }

    /**
     * @Route("/calendars/{username}/new", name="calendar_create")
     * @Route("/calendars/{username}/edit/{id}", name="calendar_edit")
     */
    public function calendarCreate(Request $request, string $username, ?int $id, TranslatorInterface $trans)
    {
        $principal = $this->get('doctrine')->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username);

        if (!$principal) {
            throw $this->createNotFoundException('User not found');
        }

        if ($id) {
            $calendarInstance = $this->get('doctrine')->getRepository(CalendarInstance::class)->findOneById($id);
            if (!$calendarInstance) {
                throw $this->createNotFoundException('Calendar not found');
            }
        } else {
            $calendarInstance = new CalendarInstance();
            $calendar = new Calendar();
            $calendarInstance->setCalendar($calendar);
        }

        $form = $this->createForm(CalendarInstanceType::class, $calendarInstance, ['new' => !$id]);

        $components = explode(',', $calendarInstance->getCalendar()->getComponents());

        $form->get('todos')->setData(in_array(Calendar::COMPONENT_TODOS, $components));
        $form->get('notes')->setData(in_array(Calendar::COMPONENT_NOTES, $components));

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $components = [Calendar::COMPONENT_EVENT]; // We always need VEVENT
            if ($form->get('todos')->getData()) {
                $components[] = Calendar::COMPONENT_TODOS;
            }
            if ($form->get('notes')->getData()) {
                $components[] = Calendar::COMPONENT_NOTES;
            }

            $calendarInstance->setPrincipalUri(Principal::PREFIX.$username);
            $calendarInstance->getCalendar()->setComponents(implode(',', $components));

            $entityManager = $this->get('doctrine')->getManager();

            $entityManager->persist($calendarInstance);
            $entityManager->flush();

            $this->addFlash('success', $trans->trans('calendar.saved'));

            return $this->redirectToRoute('calendars', ['username' => $username]);
        }

        return $this->render('calendars/edit.html.twig', [
            'form' => $form->createView(),
            'principal' => $principal,
            'username' => $username,
            'calendar' => $calendarInstance,
        ]);
    }

    /**
     * @Route("/calendars/{username}/delete/{id}", name="calendar_delete")
     */
    public function calendarDelete(string $username, string $id, TranslatorInterface $trans)
    {
        $instance = $this->get('doctrine')->getRepository(CalendarInstance::class)->findOneById($id);
        if (!$instance) {
            throw $this->createNotFoundException('Calendar not found');
        }

        $entityManager = $this->get('doctrine')->getManager();

        $calendarsSubscriptions = $this->get('doctrine')->getRepository(CalendarSubscription::class)->findByPrincipalUri($instance->getPrincipalUri());
        foreach ($calendarsSubscriptions ?? [] as $subscription) {
            $entityManager->remove($subscription);
        }

        $schedulingObjects = $this->get('doctrine')->getRepository(SchedulingObject::class)->findByPrincipalUri($instance->getPrincipalUri());
        foreach ($schedulingObjects ?? [] as $object) {
            $entityManager->remove($object);
        }

        foreach ($instance->getCalendar()->getObjects() ?? [] as $object) {
            $entityManager->remove($object);
        }
        foreach ($instance->getCalendar()->getChanges() ?? [] as $change) {
            $entityManager->remove($change);
        }
        $entityManager->remove($instance->getCalendar());
        $entityManager->remove($instance);

        $entityManager->flush();
        $this->addFlash('success', $trans->trans('calendar.deleted'));

        return $this->redirectToRoute('calendars', ['username' => $username]);
    }

    /**
     * @Route("/calendars/delegates/{id}", name="calendar_delegates", requirements={"id":"\d+"})
     */
    public function calendarDelegates(int $id)
    {
        $calendar = $this->get('doctrine')->getRepository(CalendarInstance::class)->findOneById($id);

        if (!$calendar) {
            throw $this->createNotFoundException('Calendar not found');
        }

        $principal = $this->get('doctrine')->getRepository(Principal::class)->findOneByUri($calendar->getPrincipalUri());

        $allPrincipalsExcept = $this->get('doctrine')->getRepository(Principal::class)->findAllExceptPrincipal($calendar->getPrincipalUri());

        $principalProxyRead = $this->get('doctrine')->getRepository(Principal::class)->findOneByUri($principal->getUri().Principal::READ_PROXY_SUFFIX);
        $principalProxyWrite = $this->get('doctrine')->getRepository(Principal::class)->findOneByUri($principal->getUri().Principal::WRITE_PROXY_SUFFIX);

        return $this->render('calendars/delegates.html.twig', [
            'calendar' => $calendar,
            'principal' => $principal,
            'delegation' => $principalProxyRead && $principalProxyWrite,
            'allPrincipals' => $allPrincipalsExcept,
        ]);
    }

    /**
     * @Route("/calendars/delegates/{id}/add", name="calendar_delegate_add", requirements={"id":"\d+"})
     */
    public function calendarDelegateAdd(Request $request, int $id)
    {
        $calendar = $this->get('doctrine')->getRepository(CalendarInstance::class)->findOneById($id);

        if (!$calendar) {
            throw $this->createNotFoundException('Calendar not found');
        }

        $principal = $this->get('doctrine')->getRepository(Principal::class)->findOneByUri($calendar->getPrincipalUri());

        if (!$principal) {
            throw $this->createNotFoundException('Principal linked to this calendar not found');
        }

        $newMemberToAdd = $this->get('doctrine')->getRepository(Principal::class)->findOneById($request->get('principalId'));

        if (!$newMemberToAdd) {
            throw $this->createNotFoundException('Member not found');
        }

        $principal->addDelegee($newMemberToAdd);
        $entityManager = $this->get('doctrine')->getManager();
        $entityManager->flush();

        return $this->redirectToRoute('calendar_delegates', ['id' => $id]);
    }

    /**
     * @Route("/calendars/delegates/{id}/remove/{delegateId}", name="calendar_delegate_remove", requirements={"id":"\d+", "delegateId":"\d+"})
     */
    public function calendarDelegateRemove(Request $request, int $id, int $delegateId)
    {
        $calendar = $this->get('doctrine')->getRepository(CalendarInstance::class)->findOneById($id);

        if (!$calendar) {
            throw $this->createNotFoundException('Calendar not found');
        }

        $principal = $this->get('doctrine')->getRepository(Principal::class)->findOneByUri($calendar->getPrincipalUri());

        if (!$principal) {
            throw $this->createNotFoundException('Principal linked to this calendar not found');
        }

        $memberToRemove = $this->get('doctrine')->getRepository(Principal::class)->findOneById($delegateId);

        if (!$memberToRemove) {
            throw $this->createNotFoundException('Member not found');
        }

        $principal->removeDelegee($memberToRemove);
        $entityManager = $this->get('doctrine')->getManager();
        $entityManager->flush();

        return $this->redirectToRoute('calendar_delegates', ['id' => $id]);
    }

    /**
     * @Route("/calendars/delegation/{id}/{toggle}", name="calendar_delegation_toggle", requirements={"id":"\d+", "toggle":"(on|off)"})
     */
    public function calendarToggleDelegation(int $id, string $toggle)
    {
        $calendar = $this->get('doctrine')->getRepository(CalendarInstance::class)->findOneById($id);

        if (!$calendar) {
            throw $this->createNotFoundException('Calendar not found');
        }

        $principal = $this->get('doctrine')->getRepository(Principal::class)->findOneByUri($calendar->getPrincipalUri());

        if (!$principal) {
            throw $this->createNotFoundException('Principal linked to this calendar not found');
        }

        $entityManager = $this->get('doctrine')->getManager();

        if ('on' === $toggle) {
            $principalProxyRead = new Principal();
            $principalProxyRead->setUri($principal->getUri().Principal::READ_PROXY_SUFFIX)
                               ->setIsMain(false);
            $entityManager->persist($principalProxyRead);

            $principalProxyWrite = new Principal();
            $principalProxyWrite->setUri($principal->getUri().Principal::WRITE_PROXY_SUFFIX)
                               ->setIsMain(false);
            $entityManager->persist($principalProxyWrite);
        } else {
            $principalProxyRead = $this->get('doctrine')->getRepository(Principal::class)->findOneByUri($principal->getUri().Principal::READ_PROXY_SUFFIX);
            $principalProxyRead && $entityManager->remove($principalProxyRead);

            $principalProxyWrite = $this->get('doctrine')->getRepository(Principal::class)->findOneByUri($principal->getUri().Principal::WRITE_PROXY_SUFFIX);
            $principalProxyWrite && $entityManager->remove($principalProxyWrite);

            // Remove also delegates
            $principal->removeAllDelegees();
        }

        $entityManager->flush();

        return $this->redirectToRoute('calendar_delegates', ['id' => $id]);
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
            'username' => $username,
        ]);
    }

    /**
     * @Route("/adressbooks/{username}/new", name="addressbook_create")
     * @Route("/adressbooks/{username}/edit/{id}", name="addressbook_edit")
     */
    public function addressbookCreate(Request $request, string $username, ?int $id, TranslatorInterface $trans)
    {
        $principal = $this->get('doctrine')->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username);

        if (!$principal) {
            throw $this->createNotFoundException('User not found');
        }

        if ($id) {
            $addressbook = $this->get('doctrine')->getRepository(AddressBook::class)->findOneById($id);
            if (!$addressbook) {
                throw $this->createNotFoundException('Address book not found');
            }
        } else {
            $addressbook = new AddressBook();
        }

        $form = $this->createForm(AddressBookType::class, $addressbook, ['new' => !$id]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->get('doctrine')->getManager();

            $addressbook->setPrincipalUri(Principal::PREFIX.$username);

            $entityManager->persist($addressbook);
            $entityManager->flush();

            $this->addFlash('success', $trans->trans('addressbooks.saved'));

            return $this->redirectToRoute('address_books', ['username' => $username]);
        }

        return $this->render('addressbooks/edit.html.twig', [
            'form' => $form->createView(),
            'principal' => $principal,
            'username' => $username,
            'addressbook' => $addressbook,
        ]);
    }

    /**
     * @Route("/addressbooks/{username}/delete/{id}", name="addressbook_delete")
     */
    public function addressbookDelete(string $username, string $id, TranslatorInterface $trans)
    {
        $addressbook = $this->get('doctrine')->getRepository(AddressBook::class)->findOneById($id);
        if (!$addressbook) {
            throw $this->createNotFoundException('Address Book not found');
        }

        $entityManager = $this->get('doctrine')->getManager();

        foreach ($addressbook->getCards() ?? [] as $card) {
            $entityManager->remove($card);
        }
        foreach ($addressbook->getChanges() ?? [] as $change) {
            $entityManager->remove($change);
        }
        $entityManager->remove($addressbook);

        $entityManager->flush();
        $this->addFlash('success', $trans->trans('addressbooks.deleted'));

        return $this->redirectToRoute('address_books', ['username' => $username]);
    }
}
