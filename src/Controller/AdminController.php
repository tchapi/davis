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
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminController extends AbstractController
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

    /**
     * @Route("/users", name="users")
     */
    public function users(ManagerRegistry $doctrine)
    {
        $principals = $doctrine->getRepository(Principal::class)->findByIsMain(true);

        return $this->render('users/index.html.twig', [
            'principals' => $principals,
        ]);
    }

    /**
     * @Route("/users/new", name="user_create")
     * @Route("/users/edit/{username}", name="user_edit")
     */
    public function userCreate(ManagerRegistry $doctrine, Utils $utils, Request $request, ?string $username, TranslatorInterface $trans)
    {
        if ($username) {
            $user = $doctrine->getRepository(User::class)->findOneByUsername($username);
            if (!$user) {
                throw $this->createNotFoundException('User not found');
            }
            $oldHash = $user->getPassword();
            $principal = $doctrine->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username);
        } else {
            $user = new User();
            $principal = new Principal();
        }

        $form = $this->createForm(UserType::class, $user, ['new' => !$username]);

        $form->get('displayName')->setData($principal->getDisplayName());
        $form->get('email')->setData($principal->getEmail());
        $form->get('isAdmin')->setData($principal->getIsAdmin());

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $displayName = $form->get('displayName')->getData();
            $email = $form->get('email')->getData();
            $isAdmin = $form->get('isAdmin')->getData();

            // Create password for user
            if ($username && is_null($user->getPassword())) {
                // The user is not new and does not want to change its password
                $user->setPassword($oldHash);
            } else {
                $hash = password_hash($user->getPassword(), PASSWORD_DEFAULT);
                $user->setPassword($hash);
            }

            $entityManager = $doctrine->getManager();

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

                // Enable delegation by default
                $principalProxyRead = new Principal();
                $principalProxyRead->setUri($principal->getUri().Principal::READ_PROXY_SUFFIX)
                                   ->setIsMain(false);
                $entityManager->persist($principalProxyRead);

                $principalProxyWrite = new Principal();
                $principalProxyWrite->setUri($principal->getUri().Principal::WRITE_PROXY_SUFFIX)
                                   ->setIsMain(false);
                $entityManager->persist($principalProxyWrite);

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
                      ->setEmail($email)
                      ->setIsAdmin($isAdmin);

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
    public function userDelete(ManagerRegistry $doctrine, string $username, TranslatorInterface $trans)
    {
        $user = $doctrine->getRepository(User::class)->findOneByUsername($username);
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        $entityManager = $doctrine->getManager();
        $entityManager->remove($user);

        $principal = $doctrine->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username);
        $entityManager->remove($principal);

        // Remove calendars and addressbooks
        $calendars = $doctrine->getRepository(CalendarInstance::class)->findByPrincipalUri(Principal::PREFIX.$username);
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
        $calendarsSubscriptions = $doctrine->getRepository(CalendarSubscription::class)->findByPrincipalUri(Principal::PREFIX.$username);
        foreach ($calendarsSubscriptions ?? [] as $subscription) {
            $entityManager->remove($subscription);
        }
        $schedulingObjects = $doctrine->getRepository(SchedulingObject::class)->findByPrincipalUri(Principal::PREFIX.$username);
        foreach ($schedulingObjects ?? [] as $object) {
            $entityManager->remove($object);
        }

        $addressbooks = $doctrine->getRepository(AddressBook::class)->findByPrincipalUri(Principal::PREFIX.$username);
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
     * @Route("/users/delegates/{username}", name="delegates")
     */
    public function userDelegates(ManagerRegistry $doctrine, string $username)
    {
        $principal = $doctrine->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username);

        $allPrincipalsExcept = $doctrine->getRepository(Principal::class)->findAllExceptPrincipal(Principal::PREFIX.$username);

        // Get delegates. They are not linked to the principal in itself, but to its proxies
        $principalProxyRead = $doctrine->getRepository(Principal::class)->findOneByUri($principal->getUri().Principal::READ_PROXY_SUFFIX);
        $principalProxyWrite = $doctrine->getRepository(Principal::class)->findOneByUri($principal->getUri().Principal::WRITE_PROXY_SUFFIX);

        return $this->render('users/delegates.html.twig', [
            'principal' => $principal,
            'delegation' => $principalProxyRead && $principalProxyWrite,
            'principalProxyRead' => $principalProxyRead,
            'principalProxyWrite' => $principalProxyWrite,
            'allPrincipals' => $allPrincipalsExcept,
        ]);
    }

    /**
     * @Route("/users/delegation/{username}/{toggle}", name="user_delegation_toggle", requirements={"toggle":"(on|off)"})
     */
    public function userToggleDelegation(ManagerRegistry $doctrine, string $username, string $toggle)
    {
        $principal = $doctrine->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username);

        if (!$principal) {
            throw $this->createNotFoundException('Principal not found');
        }

        $entityManager = $doctrine->getManager();

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
            $principalProxyRead = $doctrine->getRepository(Principal::class)->findOneByUri($principal->getUri().Principal::READ_PROXY_SUFFIX);
            $principalProxyRead && $entityManager->remove($principalProxyRead);

            $principalProxyWrite = $doctrine->getRepository(Principal::class)->findOneByUri($principal->getUri().Principal::WRITE_PROXY_SUFFIX);
            $principalProxyWrite && $entityManager->remove($principalProxyWrite);

            // Remove also delegates
            $principal->removeAllDelegees();
        }

        $entityManager->flush();

        return $this->redirectToRoute('delegates', ['username' => $username]);
    }

    /**
     * @Route("/users/delegates/{username}/add", name="user_delegate_add")
     */
    public function userDelegateAdd(ManagerRegistry $doctrine, Request $request, string $username)
    {
        if (!is_numeric($request->get('principalId'))) {
            throw new BadRequestHttpException();
        }

        $newMemberToAdd = $doctrine->getRepository(Principal::class)->findOneById($request->get('principalId'));

        if (!$newMemberToAdd) {
            throw $this->createNotFoundException('Member not found');
        }

        // Depending on write access or not, attach to the correct principal
        if ('true' === $request->get('write')) {
            // Let's check that there wasn't a read proxy first
            $principalProxyRead = $doctrine->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username.Principal::READ_PROXY_SUFFIX);
            if (!$principalProxyRead) {
                throw $this->createNotFoundException('Principal linked to this calendar not found');
            }
            $principalProxyRead->removeDelegee($newMemberToAdd);
            // And then add the Write access
            $principal = $doctrine->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username.Principal::WRITE_PROXY_SUFFIX);
        } else {
            $principal = $doctrine->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username.Principal::READ_PROXY_SUFFIX);
        }

        if (!$principal) {
            throw $this->createNotFoundException('Principal linked to this calendar not found');
        }

        $principal->addDelegee($newMemberToAdd);
        $entityManager = $doctrine->getManager();
        $entityManager->flush();

        return $this->redirectToRoute('delegates', ['username' => $username]);
    }

    /**
     * @Route("/users/delegates/{username}/remove/{principalProxyId}/{delegateId}", name="user_delegate_remove", requirements={"principalProxyId":"\d+", "delegateId":"\d+"})
     */
    public function userDelegateRemove(ManagerRegistry $doctrine, Request $request, string $username, int $principalProxyId, int $delegateId)
    {
        $principalProxy = $doctrine->getRepository(Principal::class)->findOneById($principalProxyId);
        if (!$principalProxy) {
            throw $this->createNotFoundException('Principal linked to this calendar not found');
        }

        $memberToRemove = $doctrine->getRepository(Principal::class)->findOneById($delegateId);
        if (!$memberToRemove) {
            throw $this->createNotFoundException('Member not found');
        }

        $principalProxy->removeDelegee($memberToRemove);
        $entityManager = $doctrine->getManager();
        $entityManager->flush();

        return $this->redirectToRoute('delegates', ['username' => $username]);
    }

    /**
     * @Route("/calendars/{username}", name="calendars")
     */
    public function calendars(ManagerRegistry $doctrine, UrlGeneratorInterface $router, string $username)
    {
        $principal = $doctrine->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username);
        $allCalendars = $doctrine->getRepository(CalendarInstance::class)->findByPrincipalUri(Principal::PREFIX.$username);

        // Separate shared calendars
        $calendars = [];
        $shared = [];
        foreach ($allCalendars as $calendar) {
            if (CalendarInstance::ACCESS_OWNER === $calendar->getAccess()) {
                $calendars[] = [
                    'entity' => $calendar,
                    'uri' => $router->generate('dav', ['path' => 'calendars/'.$username.'/'.$calendar->getUri()], UrlGeneratorInterface::ABSOLUTE_URL),
                ];
            } else {
                $shared[] = [
                    'entity' => $calendar,
                    'uri' => $router->generate('dav', ['path' => 'calendars/'.$username.'/'.$calendar->getUri()], UrlGeneratorInterface::ABSOLUTE_URL),
                ];
            }
        }

        // We need all the other users so we can propose to share calendars with them
        $allPrincipalsExcept = $doctrine->getRepository(Principal::class)->findAllExceptPrincipal(Principal::PREFIX.$username);

        return $this->render('calendars/index.html.twig', [
            'calendars' => $calendars,
            'shared' => $shared,
            'principal' => $principal,
            'username' => $username,
            'allPrincipals' => $allPrincipalsExcept,
        ]);
    }

    /**
     * @Route("/calendars/{username}/new", name="calendar_create")
     * @Route("/calendars/{username}/edit/{id}", name="calendar_edit", requirements={"id":"\d+"})
     */
    public function calendarEdit(ManagerRegistry $doctrine, Request $request, string $username, ?int $id, TranslatorInterface $trans)
    {
        $principal = $doctrine->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username);

        if (!$principal) {
            throw $this->createNotFoundException('User not found');
        }

        if ($id) {
            $calendarInstance = $doctrine->getRepository(CalendarInstance::class)->findOneById($id);
            if (!$calendarInstance) {
                throw $this->createNotFoundException('Calendar not found');
            }
        } else {
            $calendarInstance = new CalendarInstance();
            $calendar = new Calendar();
            $calendarInstance->setCalendar($calendar);
        }

        $form = $this->createForm(CalendarInstanceType::class, $calendarInstance, [
            'new' => !$id,
            'shared' => CalendarInstance::ACCESS_OWNER !== $calendarInstance->getAccess(),
        ]);

        $components = explode(',', $calendarInstance->getCalendar()->getComponents());

        $form->get('events')->setData(in_array(Calendar::COMPONENT_EVENTS, $components));
        $form->get('todos')->setData(in_array(Calendar::COMPONENT_TODOS, $components));
        $form->get('notes')->setData(in_array(Calendar::COMPONENT_NOTES, $components));
        $form->get('principalUri')->setData(Principal::PREFIX.$username);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $components = [];
            if ($form->get('events')->getData()) {
                $components[] = Calendar::COMPONENT_EVENTS;
            }
            if ($form->get('todos')->getData()) {
                $components[] = Calendar::COMPONENT_TODOS;
            }
            if ($form->get('notes')->getData()) {
                $components[] = Calendar::COMPONENT_NOTES;
            }

            $calendarInstance->getCalendar()->setComponents(implode(',', $components));

            $entityManager = $doctrine->getManager();

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
     * @Route("/calendars/{username}/shares/{calendarid}", name="calendar_shares", requirements={"calendarid":"\d+"})
     */
    public function calendarShares(ManagerRegistry $doctrine, string $username, string $calendarid, TranslatorInterface $trans)
    {
        $instances = $doctrine->getRepository(CalendarInstance::class)->findSharedInstancesOfInstance($calendarid);

        $response = [];
        foreach ($instances as $instance) {
            $response[] = [
                'principalUri' => $instance[0]['principalUri'],
                'displayName' => $instance['displayName'],
                'email' => $instance['email'],
                'accessText' => $trans->trans('calendar.share_access.'.$instance[0]['access']),
                'isWriteAccess' => CalendarInstance::ACCESS_READWRITE === $instance[0]['access'],
                'revokeUrl' => $this->generateUrl('calendar_revoke', ['username' => $username, 'id' => $instance[0]['id']]),
            ];
        }

        return new JsonResponse($response);
    }

    /**
     * @Route("/calendars/{username}/share/{instanceid}", name="calendar_share_add", requirements={"instanceid":"\d+"})
     */
    public function calendarShareAdd(ManagerRegistry $doctrine, Request $request, string $username, string $instanceid, TranslatorInterface $trans)
    {
        $instance = $doctrine->getRepository(CalendarInstance::class)->findOneById($instanceid);
        if (!$instance) {
            throw $this->createNotFoundException('Calendar not found');
        }

        if (!is_numeric($request->get('principalId'))) {
            throw new BadRequestHttpException();
        }

        $newShareeToAdd = $doctrine->getRepository(Principal::class)->findOneById($request->get('principalId'));
        if (!$newShareeToAdd) {
            throw $this->createNotFoundException('Member not found');
        }

        // Let's check that there wasn't another instance
        // already existing first, so we can update it:
        $existingSharedInstance = $doctrine->getRepository(CalendarInstance::class)->findSharedInstanceOfInstanceFor($instance->getCalendar()->getId(), $newShareeToAdd->getUri());

        $writeAccess = ('true' === $request->get('write') ? CalendarInstance::ACCESS_READWRITE : CalendarInstance::ACCESS_READ);

        $entityManager = $doctrine->getManager();

        if ($existingSharedInstance) {
            $existingSharedInstance->setAccess($writeAccess);
        } else {
            $sharedInstance = new CalendarInstance();
            $sharedInstance->setTransparent(1)
                     ->setCalendar($instance->getCalendar())
                     ->setShareHref('mailto:'.$newShareeToAdd->getEmail())
                     ->setDescription($instance->getDescription())
                     ->setDisplayName($instance->getDisplayName())
                     ->setUri(\Sabre\DAV\UUIDUtil::getUUID())
                     ->setPrincipalUri($newShareeToAdd->getUri())
                     ->setAccess($writeAccess);
            $entityManager->persist($sharedInstance);
        }

        $entityManager->flush();
        $this->addFlash('success', $trans->trans('calendar.shared'));

        return $this->redirectToRoute('calendars', ['username' => $username]);
    }

    /**
     * @Route("/calendars/{username}/delete/{id}", name="calendar_delete", requirements={"id":"\d+"})
     */
    public function calendarDelete(ManagerRegistry $doctrine, string $username, string $id, TranslatorInterface $trans)
    {
        $instance = $doctrine->getRepository(CalendarInstance::class)->findOneById($id);
        if (!$instance) {
            throw $this->createNotFoundException('Calendar not found');
        }

        $entityManager = $doctrine->getManager();

        $calendarsSubscriptions = $doctrine->getRepository(CalendarSubscription::class)->findByPrincipalUri($instance->getPrincipalUri());
        foreach ($calendarsSubscriptions ?? [] as $subscription) {
            $entityManager->remove($subscription);
        }

        $schedulingObjects = $doctrine->getRepository(SchedulingObject::class)->findByPrincipalUri($instance->getPrincipalUri());
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
     * @Route("/calendars/{username}/revoke/{id}", name="calendar_revoke", requirements={"id":"\d+"})
     */
    public function calendarRevoke(ManagerRegistry $doctrine, string $username, string $id, TranslatorInterface $trans)
    {
        $instance = $doctrine->getRepository(CalendarInstance::class)->findOneById($id);
        if (!$instance) {
            throw $this->createNotFoundException('Calendar not found');
        }

        $entityManager = $doctrine->getManager();
        $entityManager->remove($instance);

        $entityManager->flush();
        $this->addFlash('success', $trans->trans('calendar.revoked'));

        return $this->redirectToRoute('calendars', ['username' => $username]);
    }

    /**
     * @Route("/adressbooks/{username}", name="address_books")
     */
    public function addressBooks(ManagerRegistry $doctrine, string $username)
    {
        $principal = $doctrine->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username);
        $addressbooks = $doctrine->getRepository(AddressBook::class)->findByPrincipalUri(Principal::PREFIX.$username);

        return $this->render('addressbooks/index.html.twig', [
            'addressbooks' => $addressbooks,
            'principal' => $principal,
            'username' => $username,
        ]);
    }

    /**
     * @Route("/adressbooks/{username}/new", name="addressbook_create")
     * @Route("/adressbooks/{username}/edit/{id}", name="addressbook_edit", requirements={"id":"\d+"})
     */
    public function addressbookCreate(ManagerRegistry $doctrine, Request $request, string $username, ?int $id, TranslatorInterface $trans)
    {
        $principal = $doctrine->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username);

        if (!$principal) {
            throw $this->createNotFoundException('User not found');
        }

        if ($id) {
            $addressbook = $doctrine->getRepository(AddressBook::class)->findOneById($id);
            if (!$addressbook) {
                throw $this->createNotFoundException('Address book not found');
            }
        } else {
            $addressbook = new AddressBook();
        }

        $form = $this->createForm(AddressBookType::class, $addressbook, ['new' => !$id]);

        $form->get('principalUri')->setData(Principal::PREFIX.$username);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $doctrine->getManager();

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
     * @Route("/addressbooks/{username}/delete/{id}", name="addressbook_delete", requirements={"id":"\d+"})
     */
    public function addressbookDelete(ManagerRegistry $doctrine, string $username, string $id, TranslatorInterface $trans)
    {
        $addressbook = $doctrine->getRepository(AddressBook::class)->findOneById($id);
        if (!$addressbook) {
            throw $this->createNotFoundException('Address Book not found');
        }

        $entityManager = $doctrine->getManager();

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
