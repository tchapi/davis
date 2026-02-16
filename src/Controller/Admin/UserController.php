<?php

namespace App\Controller\Admin;

use App\Entity\AddressBook;
use App\Entity\Calendar;
use App\Entity\CalendarInstance;
use App\Entity\CalendarSubscription;
use App\Entity\Principal;
use App\Entity\SchedulingObject;
use App\Entity\User;
use App\Form\UserType;
use App\Services\Utils;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/users', name: 'user_')]
class UserController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function users(ManagerRegistry $doctrine): Response
    {
        $results = $doctrine->getRepository(Principal::class)->findAllMainPrincipalsWithUserIds();

        return $this->render('users/index.html.twig', [
            'results' => $results,
        ]);
    }

    #[Route('/new', name: 'create')]
    #[Route('/edit/{userId}', name: 'edit')]
    public function userCreate(ManagerRegistry $doctrine, Utils $utils, Request $request, ?int $userId, TranslatorInterface $trans): Response
    {
        if ($userId) {
            $user = $doctrine->getRepository(User::class)->findOneById($userId);
            if (!$user) {
                throw $this->createNotFoundException('User not found');
            }
            $oldHash = $user->getPassword();
            $principal = $doctrine->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$user->getUsername());
        } else {
            $user = new User();
            $principal = new Principal();
        }

        $form = $this->createForm(UserType::class, $user, ['new' => !$userId]);

        $form->get('displayName')->setData($principal->getDisplayName());
        $form->get('email')->setData($principal->getEmail());
        $form->get('isAdmin')->setData($principal->getIsAdmin());

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $displayName = $form->get('displayName')->getData();
            $email = $form->get('email')->getData();
            $isAdmin = $form->get('isAdmin')->getData();

            // Create password for user
            if ($userId && is_null($user->getPassword())) {
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

            return $this->redirectToRoute('user_index');
        }

        return $this->render('users/edit.html.twig', [
            'form' => $form->createView(),
            'userId' => $userId,
        ]);
    }

    #[Route('/delete/{userId}', name: 'delete')]
    public function userDelete(ManagerRegistry $doctrine, string $userId, TranslatorInterface $trans): Response
    {
        $user = $doctrine->getRepository(User::class)->findOneById($userId);
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        $entityManager = $doctrine->getManager();
        $entityManager->remove($user);

        $principal = $doctrine->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$user->getUsername());
        $principalProxyRead = $doctrine->getRepository(Principal::class)->findOneByUri($principal->getUri().Principal::READ_PROXY_SUFFIX);
        $principalProxyWrite = $doctrine->getRepository(Principal::class)->findOneByUri($principal->getUri().Principal::WRITE_PROXY_SUFFIX);

        $entityManager->remove($principal);
        $entityManager->remove($principalProxyRead);
        $entityManager->remove($principalProxyWrite);

        $principalUri = Principal::PREFIX.$user->getUsername();

        // Remove calendars and addressbooks
        $calendars = $doctrine->getRepository(CalendarInstance::class)->findByPrincipalUri($principalUri);
        foreach ($calendars ?? [] as $instance) {
            // We're only removing the calendar objects / changes / and calendar if the deleted user is an owner,
            // which means that the underlying calendar instance should not have another principal as owner.
            $hasDifferentOwner = $doctrine->getRepository(CalendarInstance::class)->hasDifferentOwner($instance->getCalendar()->getId(), $principalUri);
            if (!$hasDifferentOwner) {
                foreach ($instance->getCalendar()->getObjects() ?? [] as $object) {
                    $entityManager->remove($object);
                }
                foreach ($instance->getCalendar()->getChanges() ?? [] as $change) {
                    $entityManager->remove($change);
                }
                // We need to remove the shared versions of this calendar, too
                foreach ($instance->getCalendar()->getInstances() ?? [] as $instances) {
                    $entityManager->remove($instances);
                }
                $entityManager->remove($instance->getCalendar());
            }
            $entityManager->remove($instance);
        }
        $calendarsSubscriptions = $doctrine->getRepository(CalendarSubscription::class)->findByPrincipalUri($principalUri);
        foreach ($calendarsSubscriptions ?? [] as $subscription) {
            $entityManager->remove($subscription);
        }
        $schedulingObjects = $doctrine->getRepository(SchedulingObject::class)->findByPrincipalUri($principalUri);
        foreach ($schedulingObjects ?? [] as $object) {
            $entityManager->remove($object);
        }

        $addressbooks = $doctrine->getRepository(AddressBook::class)->findByPrincipalUri($principalUri);
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

        return $this->redirectToRoute('user_index');
    }

    #[Route('/delegates/{userId}', name: 'delegates')]
    public function userDelegates(ManagerRegistry $doctrine, string $userId): Response
    {
        $user = $doctrine->getRepository(User::class)->findOneById($userId);
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        $principalUri = Principal::PREFIX.$user->getUsername();

        $principal = $doctrine->getRepository(Principal::class)->findOneByUri($principalUri);

        $allPrincipalsExcept = $doctrine->getRepository(Principal::class)->findAllExceptPrincipal($principalUri);

        // Get delegates. They are not linked to the principal in itself, but to its proxies
        $principalProxyRead = $doctrine->getRepository(Principal::class)->findOneByUri($principal->getUri().Principal::READ_PROXY_SUFFIX);
        $principalProxyWrite = $doctrine->getRepository(Principal::class)->findOneByUri($principal->getUri().Principal::WRITE_PROXY_SUFFIX);

        return $this->render('users/delegates.html.twig', [
            'principal' => $principal,
            'userId' => $userId,
            'delegation' => $principalProxyRead && $principalProxyWrite,
            'principalProxyRead' => $principalProxyRead,
            'principalProxyWrite' => $principalProxyWrite,
            'allPrincipals' => $allPrincipalsExcept,
        ]);
    }

    #[Route('/delegation/{userId}/{toggle}', name: 'delegation_toggle', requirements: ['toggle' => '(on|off)'])]
    public function userToggleDelegation(ManagerRegistry $doctrine, string $userId, string $toggle): Response
    {
        $user = $doctrine->getRepository(User::class)->findOneById($userId);
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        $principalUri = Principal::PREFIX.$user->getUsername();

        $principal = $doctrine->getRepository(Principal::class)->findOneByUri($principalUri);

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

        return $this->redirectToRoute('user_delegates', ['userId' => $userId]);
    }

    #[Route('/delegates/{userId}/add', name: 'delegate_add')]
    public function userDelegateAdd(ManagerRegistry $doctrine, Request $request, string $userId): Response
    {
        if (!is_numeric($request->get('principalId'))) {
            throw new BadRequestHttpException();
        }

        $user = $doctrine->getRepository(User::class)->findOneById($userId);
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        $principalUri = Principal::PREFIX.$user->getUsername();

        $newMemberToAdd = $doctrine->getRepository(Principal::class)->findOneById($request->get('principalId'));

        if (!$newMemberToAdd) {
            throw $this->createNotFoundException('Member not found');
        }

        // Depending on write access or not, attach to the correct principal
        if ('true' === $request->get('write')) {
            // Let's check that there wasn't a read proxy first
            $principalProxyRead = $doctrine->getRepository(Principal::class)->findOneByUri($principalUri.Principal::READ_PROXY_SUFFIX);
            if (!$principalProxyRead) {
                throw $this->createNotFoundException('Principal linked to this calendar not found');
            }
            $principalProxyRead->removeDelegee($newMemberToAdd);
            // And then add the Write access
            $principal = $doctrine->getRepository(Principal::class)->findOneByUri($principalUri.Principal::WRITE_PROXY_SUFFIX);
        } else {
            $principal = $doctrine->getRepository(Principal::class)->findOneByUri($principalUri.Principal::READ_PROXY_SUFFIX);
        }

        if (!$principal) {
            throw $this->createNotFoundException('Principal linked to this calendar not found');
        }

        $principal->addDelegee($newMemberToAdd);
        $entityManager = $doctrine->getManager();
        $entityManager->flush();

        return $this->redirectToRoute('user_delegates', ['userId' => $userId]);
    }

    #[Route('/delegates/{userId}/remove/{principalProxyId}/{delegateId}', name: 'delegate_remove', requirements: ['principalProxyId' => "\d+", 'delegateId' => "\d+"])]
    public function userDelegateRemove(ManagerRegistry $doctrine, Request $request, string $userId, int $principalProxyId, int $delegateId): Response
    {
        $user = $doctrine->getRepository(User::class)->findOneById($userId);
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

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

        return $this->redirectToRoute('user_delegates', ['userId' => $userId]);
    }
}
