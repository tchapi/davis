<?php

namespace App\Controller\Admin;

use App\Entity\Calendar;
use App\Entity\CalendarInstance;
use App\Entity\CalendarSubscription;
use App\Entity\Principal;
use App\Entity\User;
use App\Form\CalendarInstanceType;
use Doctrine\Persistence\ManagerRegistry;
use Sabre\DAV\Sharing\Plugin as SharingPlugin;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/calendars', name: 'calendar_')]
class CalendarController extends AbstractController
{
    #[Route('/{userId}', name: 'index')]
    public function calendars(ManagerRegistry $doctrine, UrlGeneratorInterface $router, #[MapEntity(id: 'userId')] User $user, int $userId): Response
    {
        $username = $user->getUsername();
        $principalUri = $user->getPrincipalUri();

        $principal = $doctrine->getRepository(Principal::class)->findOneByUri($principalUri);
        $allCalendars = $doctrine->getRepository(CalendarInstance::class)->findByPrincipalUri($principalUri);

        $subscriptions = $doctrine->getRepository(CalendarSubscription::class)->findByPrincipalUri($principalUri);

        // Separate shared calendars
        $calendars = [];
        $shared = [];
        $auto = [];
        foreach ($allCalendars as $calendar) {
            if ($calendar->isAutomaticallyGenerated()) {
                $auto[] = [
                    'entity' => $calendar,
                    'uri' => $router->generate('dav', ['path' => 'calendars/'.$username.'/'.$calendar->getUri()], UrlGeneratorInterface::ABSOLUTE_URL),
                ];
            } elseif (!$calendar->isShared()) {
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
        $allPrincipalsExcept = $doctrine->getRepository(Principal::class)->findAllExceptPrincipal($principalUri);

        return $this->render('calendars/index.html.twig', [
            'calendars' => $calendars,
            'subscriptions' => $subscriptions,
            'shared' => $shared,
            'auto' => $auto,
            'principal' => $principal,
            'userId' => $userId,
            'allPrincipals' => $allPrincipalsExcept,
        ]);
    }

    #[Route('/{userId}/new', name: 'create')]
    #[Route('/{userId}/edit/{id}', name: 'edit', requirements: ['id' => "\d+"])]
    public function calendarEdit(ManagerRegistry $doctrine, Request $request, #[MapEntity(id: 'userId')] User $user, int $userId, ?int $id, TranslatorInterface $trans): Response
    {
        $principalUri = $user->getPrincipalUri();

        $principal = $doctrine->getRepository(Principal::class)->findOneByUri($principalUri);

        if (!$principal) {
            throw $this->createNotFoundException('User not found');
        }

        if ($id) {
            $calendarInstance = $doctrine->getRepository(CalendarInstance::class)->findOneForPrincipal($id, $principalUri);
            if (!$calendarInstance) {
                throw $this->createNotFoundException('Calendar not found');
            }
        } else {
            $calendarInstance = new CalendarInstance();
            $calendar = new Calendar();
            $calendarInstance->setCalendar($calendar);
            // The owner is given by the URL, never by the submitted form
            $calendarInstance->setPrincipalUri($principalUri);
        }

        $arePublicCalendarsEnabled = $this->getParameter('public_calendars_enabled');

        $form = $this->createForm(CalendarInstanceType::class, $calendarInstance, [
            'new' => !$id,
            'shared' => $calendarInstance->isShared(),
            'public_calendars_enabled' => $arePublicCalendarsEnabled,
        ]);

        $components = explode(',', $calendarInstance->getCalendar()->getComponents());

        $form->get('events')->setData(in_array(Calendar::COMPONENT_EVENTS, $components));
        $form->get('todos')->setData(in_array(Calendar::COMPONENT_TODOS, $components));
        $form->get('notes')->setData(in_array(Calendar::COMPONENT_NOTES, $components));

        $form->handleRequest($request);

        $entityManager = $doctrine->getManager();

        if ($form->isSubmitted() && $form->isValid()) {
            // Only owners can change those
            if (!$calendarInstance->isShared()) {
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
                if ($arePublicCalendarsEnabled && true === $form->get('public')->getData()) {
                    $calendarInstance->setPublic(true);
                } else {
                    $calendarInstance->setPublic(false);
                }

                $calendarInstance->getCalendar()->setComponents(implode(',', $components));
            }

            // We want to remove all shares if a calendar goes public
            if ($arePublicCalendarsEnabled && true === $form->get('public')->getData() && $id) {
                $calendarId = $calendarInstance->getCalendar()->getId();
                $instances = $doctrine->getRepository(CalendarInstance::class)->findSharedInstancesOfInstance($calendarId, false);
                foreach ($instances as $instance) {
                    $entityManager->remove($instance);
                }
            }

            $entityManager->persist($calendarInstance);
            $entityManager->flush();

            $this->addFlash('success', $trans->trans('calendar.saved'));

            return $this->redirectToRoute('calendar_index', ['userId' => $userId]);
        }

        return $this->render('calendars/edit.html.twig', [
            'form' => $form->createView(),
            'principal' => $principal,
            'userId' => $userId,
            'calendar' => $calendarInstance,
        ]);
    }

    #[Route('/{userId}/shares/{calendarid}', name: 'shares', requirements: ['calendarid' => "\d+"])]
    public function calendarShares(ManagerRegistry $doctrine, #[MapEntity(id: 'userId')] User $user, int $userId, string $calendarid, TranslatorInterface $trans): Response
    {
        $principalUri = $user->getPrincipalUri();

        if (!$doctrine->getRepository(CalendarInstance::class)->findOwnerInstanceOfCalendarForPrincipal((int) $calendarid, $principalUri)) {
            throw $this->createNotFoundException('Calendar not found');
        }

        $instances = $doctrine->getRepository(CalendarInstance::class)->findSharedInstancesOfInstance($calendarid, true);

        $response = [];
        foreach ($instances as $instance) {
            $response[] = [
                'principalUri' => $instance[0]['principalUri'],
                'displayName' => $instance['displayName'],
                'email' => $instance['email'],
                'accessText' => $trans->trans('calendar.share_access.'.$instance[0]['access']),
                'isWriteAccess' => SharingPlugin::ACCESS_READWRITE === $instance[0]['access'],
                'revokeUrl' => $this->generateUrl('calendar_revoke', ['userId' => $userId, 'id' => $instance[0]['id']]),
            ];
        }

        return new JsonResponse($response);
    }

    #[Route('/{userId}/share/{instanceid}', name: 'share_add', requirements: ['instanceid' => "\d+"], methods: ['POST'])]
    public function calendarShareAdd(ManagerRegistry $doctrine, Request $request, #[MapEntity(id: 'userId')] User $user, int $userId, string $instanceid, TranslatorInterface $trans): Response
    {
        if (!$this->isCsrfTokenValid('admin_action', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $principalUri = $user->getPrincipalUri();

        // Only the owner of a calendar can share it
        $instance = $doctrine->getRepository(CalendarInstance::class)->findOneForPrincipal((int) $instanceid, $principalUri);
        if (!$instance || $instance->isShared()) {
            throw $this->createNotFoundException('Calendar not found');
        }

        if (!is_numeric($request->request->get('principalId'))) {
            throw new BadRequestHttpException();
        }

        $newShareeToAdd = $doctrine->getRepository(Principal::class)->findOneById($request->request->get('principalId'));
        if (!$newShareeToAdd) {
            throw $this->createNotFoundException('Member not found');
        }
        if ($newShareeToAdd->getUri() === $principalUri) {
            throw new BadRequestHttpException('A calendar cannot be shared with its owner');
        }

        // Let's check that there wasn't another instance
        // already existing first, so we can update it:
        $existingSharedInstance = $doctrine->getRepository(CalendarInstance::class)->findSharedInstanceOfInstanceFor($instance->getCalendar()->getId(), $newShareeToAdd->getUri());

        $writeAccess = ('true' === $request->request->get('write') ? SharingPlugin::ACCESS_READWRITE : SharingPlugin::ACCESS_READ);

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
                     ->setCalendarColor($instance->getCalendarColor())
                     ->setUri(\Sabre\DAV\UUIDUtil::getUUID())
                     ->setPrincipalUri($newShareeToAdd->getUri())
                     ->setAccess($writeAccess);
            $entityManager->persist($sharedInstance);
        }

        $entityManager->flush();
        $this->addFlash('success', $trans->trans('calendar.shared'));

        return $this->redirectToRoute('calendar_index', ['userId' => $userId]);
    }

    #[Route('/{userId}/delete/{id}', name: 'delete', requirements: ['id' => "\d+"], methods: ['POST'])]
    public function calendarDelete(ManagerRegistry $doctrine, Request $request, #[MapEntity(id: 'userId')] User $user, int $userId, string $id, TranslatorInterface $trans): Response
    {
        if (!$this->isCsrfTokenValid('admin_action', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $principalUri = $user->getPrincipalUri();

        $instance = $doctrine->getRepository(CalendarInstance::class)->findOneForPrincipal((int) $id, $principalUri);
        if (!$instance) {
            throw $this->createNotFoundException('Calendar not found');
        }

        $entityManager = $doctrine->getManager();

        // A calendar shared *with* this user is not theirs to delete: only drop their access to it
        if ($instance->isShared()) {
            $entityManager->remove($instance);
            $entityManager->flush();
            $this->addFlash('success', $trans->trans('calendar.revoked'));

            return $this->redirectToRoute('calendar_index', ['userId' => $userId]);
        }

        // Scheduling objects attached to the calendar objects of the calendar
        $schedulingObjectsOfCalendarObjects = $doctrine->getRepository(CalendarInstance::class)->findAllSchedulingObjectsForCalendar($instance->getId(), $principalUri);
        foreach ($schedulingObjectsOfCalendarObjects ?? [] as $object) {
            $entityManager->remove($object);
        }
        foreach ($instance->getCalendar()->getObjects() ?? [] as $object) {
            $entityManager->remove($object);
        }
        foreach ($instance->getCalendar()->getChanges() ?? [] as $change) {
            $entityManager->remove($change);
        }

        // Remove the original calendar instance
        $entityManager->remove($instance);

        // Remove shared instances
        $sharedInstances = $doctrine->getRepository(CalendarInstance::class)->findSharedInstancesOfInstance($instance->getCalendar()->getId(), false);
        foreach ($sharedInstances as $sharedInstance) {
            $entityManager->remove($sharedInstance);
        }

        // Finally remove the calendar itself
        $entityManager->remove($instance->getCalendar());

        $entityManager->flush();
        $this->addFlash('success', $trans->trans('calendar.deleted'));

        return $this->redirectToRoute('calendar_index', ['userId' => $userId]);
    }

    #[Route('/{userId}/revoke/{id}', name: 'revoke', requirements: ['id' => "\d+"], methods: ['POST'])]
    public function calendarRevoke(ManagerRegistry $doctrine, Request $request, #[MapEntity(id: 'userId')] User $user, int $userId, string $id, TranslatorInterface $trans): Response
    {
        if (!$this->isCsrfTokenValid('admin_action', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $principalUri = $user->getPrincipalUri();

        $repository = $doctrine->getRepository(CalendarInstance::class);
        $instance = $repository->find((int) $id);
        if (!$instance) {
            throw $this->createNotFoundException('Calendar not found');
        }

        // A share can be revoked by the sharee (from their own page) or by an owner of the calendar
        $isSharee = $instance->getPrincipalUri() === $principalUri;
        $isOwner = null !== $repository->findOwnerInstanceOfCalendarForPrincipal($instance->getCalendar()->getId(), $principalUri);
        if (!$isSharee && !$isOwner) {
            throw $this->createNotFoundException('Calendar not found');
        }

        // Revoking an owner's own instance would orphan the calendar
        if (!$instance->isShared()) {
            throw new BadRequestHttpException('Only a shared calendar can be revoked');
        }

        $entityManager = $doctrine->getManager();
        $entityManager->remove($instance);

        $entityManager->flush();
        $this->addFlash('success', $trans->trans('calendar.revoked'));

        return $this->redirectToRoute('calendar_index', ['userId' => $userId]);
    }
}
