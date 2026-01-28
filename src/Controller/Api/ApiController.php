<?php

namespace App\Controller\Api;

use App\Entity\Calendar;
use App\Entity\CalendarInstance;
use App\Entity\CalendarSubscription;
use App\Entity\Principal;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_')]
class ApiController extends AbstractController
{
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    private function validateApiKey(Request $request): bool
    {
        $key = $request->headers->get('X-API-Key');

        return hash_equals($this->apiKey, $key ?? '');
    }

    private function validateUsername(string $username): bool
    {
        return !empty($username) && is_string($username) && !preg_match('/[^a-zA-Z0-9_-]/', $username);
    }

    /**
     * Health check endpoint.
     *
     * @param Request $request The HTTP GET request
     *
     * @return JsonResponse A JSON response indicating the health status
     */
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function healthCheck(Request $request): JsonResponse
    {
        return $this->json(['status' => 'OK', 'timestamp' => date('c')], 200);
    }

    /**
     * Retrieves a list of users (with their id, uri, username, and displayname).
     *
     * @param Request $request The HTTP GET request
     *
     * @return JsonResponse A JSON response containing the list of users
     */
    #[Route('/users', name: 'users', methods: ['GET'])]
    public function getUsers(Request $request, ManagerRegistry $doctrine): JsonResponse
    {
        if (!$this->validateApiKey($request)) {
            return $this->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $principals = $doctrine->getRepository(Principal::class)->findByIsMain(true);

        $users = [];
        foreach ($principals as $principal) {
            $users[] = [
                'id' => $principal->getId(),
                'uri' => $principal->getUri(),
                'username' => $principal->getUsername(),
            ];
        }

        $response = [
            'status' => 'success',
            'data' => $users ?? [],
        ];

        return $this->json($response, 200);
    }

    /**
     * Retrieves details of a specific user (id, uri, username, displayname, email).
     *
     * @param Request $request  The HTTP GET request
     * @param string  $username The username of the user whose details are to be retrieved
     *
     * @return JsonResponse A JSON response containing the user details
     */
    #[Route('/users/{username}', name: 'user_detail', methods: ['GET'], requirements: ['username' => "[a-zA-Z0-9_-]+"])]
    public function getUserDetails(Request $request, ManagerRegistry $doctrine, string $username): JsonResponse
    {
        if (!$this->validateApiKey($request)) {
            return $this->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $user = $doctrine->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username);

        if (!$user) {
            return $this->json(['status' => 'error', 'message' => 'User Not Found'], 404);
        }

        $data = [
            'id' => $user->getId(),
            'uri' => $user->getUri(),
            'username' => $user->getUsername(),
            'displayname' => $user->getDisplayName(),
            'email' => $user->getEmail(),
        ];

        $response = [
            'status' => 'success',
            'data' => $data,
        ];

        return $this->json($response, 200);
    }

    /**
     * Retrieves a list of calendars for a specific user, including user calendars, shared calendars, and subscriptions.
     *
     * @param Request $request  The HTTP GET request
     * @param string  $username The username of the user whose calendars are to be retrieved
     *
     * @return JsonResponse A JSON response containing the list of calendars for the specified user
     */
    #[Route('/calendars/{username}', name: 'calendars', methods: ['GET'], requirements: ['username' => "[a-zA-Z0-9_-]+"])]
    public function getUserCalendars(Request $request, string $username, ManagerRegistry $doctrine): JsonResponse
    {
        if (!$this->validateApiKey($request)) {
            return $this->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $allCalendars = $doctrine->getRepository(CalendarInstance::class)->findByPrincipalUri(Principal::PREFIX.$username);
        $subscriptions = $doctrine->getRepository(CalendarSubscription::class)->findByPrincipalUri(Principal::PREFIX.$username);

        if (!$allCalendars && !$subscriptions) {
            return $this->json(['status' => 'success', 'data' => []], 200);
        }

        foreach ($allCalendars as $calendar) {
            if (!$calendar->isShared()) {
                $calendars[] = [
                    'id' => $calendar->getId(),
                    'uri' => $calendar->getUri(),
                    'displayname' => $calendar->getDisplayName(),
                    'description' => $calendar->getDescription(),
                    'events' => count($calendar->getCalendar()->getObjects()->filter(fn ($obj) => Calendar::COMPONENT_EVENTS === $obj->getComponentType())),
                    'notes' => count($calendar->getCalendar()->getObjects()->filter(fn ($obj) => Calendar::COMPONENT_NOTES === $obj->getComponentType())),
                    'tasks' => count($calendar->getCalendar()->getObjects()->filter(fn ($obj) => Calendar::COMPONENT_TODOS === $obj->getComponentType())),
                ];
            } else {
                $sharedCalendars[] = [
                    'id' => $calendar->getId(),
                    'uri' => $calendar->getUri(),
                    'displayname' => $calendar->getDisplayName(),
                    'description' => $calendar->getDescription(),
                    'events' => count($calendar->getCalendar()->getObjects()->filter(fn ($obj) => Calendar::COMPONENT_EVENTS === $obj->getComponentType())),
                    'notes' => count($calendar->getCalendar()->getObjects()->filter(fn ($obj) => Calendar::COMPONENT_NOTES === $obj->getComponentType())),
                    'tasks' => count($calendar->getCalendar()->getObjects()->filter(fn ($obj) => Calendar::COMPONENT_TODOS === $obj->getComponentType())),
                ];
            }
        }

        foreach ($subscriptions as $subscription) {
            $calendars[] = [
                'id' => $subscription->getId(),
                'uri' => $subscription->getUri(),
                'displayname' => $subscription->getDisplayName(),
                'description' => $subscription->getDescription(),
                'events' => count($subscription->getCalendar()->getObjects()->filter(fn ($obj) => Calendar::COMPONENT_EVENTS === $obj->getComponentType())),
                'notes' => count($subscription->getCalendar()->getObjects()->filter(fn ($obj) => Calendar::COMPONENT_NOTES === $obj->getComponentType())),
                'tasks' => count($subscription->getCalendar()->getObjects()->filter(fn ($obj) => Calendar::COMPONENT_TODOS === $obj->getComponentType())),
            ];
        }

        $response = [
            'status' => 'success',
            'data' => [
                'user_calendars' => $calendars ?? [],
                'shared_calendars' => $sharedCalendars ?? [],
                'subscriptions' => $subscriptions ?? [],
            ],
        ];

        return $this->json($response, 200);
    }

    /**
     * Retrieves details of a specific calendar for a specific user (id, uri, displayname, description, number of events, notes, and tasks).
     *
     * @param Request $request     The HTTP GET request
     * @param string  $username    The username of the user whose calendar details are to be retrieved
     * @param int     $calendar_id The ID of the calendar whose details are to be retrieved
     *
     * @return JsonResponse A JSON response containing the calendar details
     */
    #[Route('/calendars/{username}/{calendar_id}', name: 'calendar_details', methods: ['GET'], requirements: ['calendar_id' => "\d+", 'username' => "[a-zA-Z0-9_-]+"])]
    public function getUserCalendarDetails(Request $request, string $username, int $calendar_id, ManagerRegistry $doctrine): JsonResponse
    {
        if (!$this->validateApiKey($request)) {
            return $this->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $allCalendars = $doctrine->getRepository(CalendarInstance::class)->findByPrincipalUri(Principal::PREFIX.$username);

        if (!$allCalendars) {
            return $this->json(['status' => 'success', 'data' => []], 200);
        }

        foreach ($allCalendars as $calendar) {
            if (!$calendar->isShared() && $calendar->getId() === $calendar_id) {
                $calendar_details = [
                    'id' => $calendar->getId(),
                    'uri' => $calendar->getUri(),
                    'displayname' => $calendar->getDisplayName(),
                    'description' => $calendar->getDescription(),
                    'events' => count($calendar->getCalendar()->getObjects()->filter(fn ($obj) => Calendar::COMPONENT_EVENTS === $obj->getComponentType())),
                    'notes' => count($calendar->getCalendar()->getObjects()->filter(fn ($obj) => Calendar::COMPONENT_NOTES === $obj->getComponentType())),
                    'tasks' => count($calendar->getCalendar()->getObjects()->filter(fn ($obj) => Calendar::COMPONENT_TODOS === $obj->getComponentType())),
                ];
            }
        }

        $response = [
            'status' => 'success',
            'data' => $calendar_details ?? [],
        ];

        return $this->json($response, 200);
    }

    /**
     * Retrieves a list of shares for a specific calendar of a specific user (id, username, displayname, email, write_access).
     *
     * @param Request $request     The HTTP GET request
     * @param string  $username    The username of the user whose calendar shares are to be retrieved
     * @param string  $calendar_id The ID of the calendar whose shares are to be retrieved
     *
     * @return JsonResponse A JSON response containing the list of calendar shares
     */
    #[Route('/calendars/{username}/shares/{calendar_id}', name: 'calendars_shares', methods: ['GET'], requirements: ['calendar_id' => "\d+", 'username' => "[a-zA-Z0-9_-]+"])]
    public function getUserCalendarsShares(Request $request, string $username, int $calendar_id, ManagerRegistry $doctrine): JsonResponse
    {
        if (!$this->validateApiKey($request)) {
            return $this->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $ownerInstance = $doctrine->getRepository(CalendarInstance::class)->findOneBy([
            'id' => $calendar_id,
            'principalUri' => Principal::PREFIX.$username,
        ]);

        if (!$ownerInstance) {
            return $this->json(['status' => 'error', 'message' => 'Invalid Calendar ID/Username'], 400);
        }

        $instances = $doctrine->getRepository(CalendarInstance::class)->findSharedInstancesOfInstance($calendar_id, true);

        if (!$instances) {
            return $this->json(['status' => 'success', 'data' => []], 200);
        }

        foreach ($instances as $instance) {
            $user_id = $doctrine->getRepository(Principal::class)->findOneByUri($instance[0]['principalUri']);

            $calendars[] = [
                'username' => mb_substr($instance[0]['principalUri'], strlen(Principal::PREFIX)),
                'user_id' => $user_id?->getId() ?? null,
                'displayname' => $instance['displayName'],
                'email' => $instance['email'],
                'write_access' => CalendarInstance::ACCESS_READWRITE === $instance[0]['access'],
            ];
        }

        $response = [
            'status' => 'success',
            'data' => $calendars ?? [],
        ];

        return $this->json($response, 200);
    }

    /**
     * Sets or updates a share for a specific calendar of a specific user.
     *
     * @param Request $request     The HTTP POST request
     * @param string  $username    The username of the user whose calendar share is to be set or updated
     * @param string  $calendar_id The ID of the calendar whose share is to be set or updated
     *
     * @return JsonResponse A JSON response indicating the success or failure of the operation
     */
    #[Route('/calendars/{username}/share/{calendar_id}/add', name: 'calendars_share', methods: ['POST'], requirements: ['calendar_id' => "\d+", 'username' => "[a-zA-Z0-9_-]+"])]
    public function setUserCalendarsShare(Request $request, string $username, int $calendar_id, ManagerRegistry $doctrine): JsonResponse
    {
        if (!$this->validateApiKey($request)) {
            return $this->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $ownerInstance = $doctrine->getRepository(CalendarInstance::class)->findOneBy([
            'id' => $calendar_id,
            'principalUri' => Principal::PREFIX.$username,
        ]);

        if (!$ownerInstance) {
            return $this->json(['status' => 'error', 'message' => 'Invalid Calendar ID/Username'], 400);
        }

        $userId = $request->get('user_id');
        $writeAccess = $request->get('write_access');
        if (!is_numeric($userId) || !in_array($writeAccess, ['true', 'false'], true)) {
            return $this->json(['status' => 'error', 'message' => 'Invalid Sharee ID/Write Access Value'], 400);
        }

        $instance = $doctrine->getRepository(CalendarInstance::class)->findOneById($calendar_id);
        $newShareeToAdd = $doctrine->getRepository(Principal::class)->findOneById($userId);

        if (!$instance || !$newShareeToAdd) {
            return $this->json(['status' => 'error', 'message' => 'Calendar Instance/User Not Found'], 404);
        }

        $existingSharedInstance = $doctrine->getRepository(CalendarInstance::class)->findSharedInstanceOfInstanceFor($instance->getCalendar()->getId(), $newShareeToAdd->getUri());
        $accessLevel = ('true' === $writeAccess ? CalendarInstance::ACCESS_READWRITE : CalendarInstance::ACCESS_READ);
        $entityManager = $doctrine->getManager();

        if ($existingSharedInstance) {
            $existingSharedInstance->setAccess($accessLevel);
        } else {
            $sharedInstance = new CalendarInstance();
            $sharedInstance->setTransparent(1)
                     ->setCalendar($instance->getCalendar())
                     ->setShareHref('mailto:'.$newShareeToAdd->getEmail())
                     ->setDescription($instance->getDescription())
                     ->setDisplayName($instance->getDisplayName())
                     ->setUri(\Sabre\DAV\UUIDUtil::getUUID())
                     ->setPrincipalUri($newShareeToAdd->getUri())
                     ->setAccess($accessLevel);
            $entityManager->persist($sharedInstance);
        }
        $entityManager->flush();

        return $this->json(['status' => 'success'], 200);
    }

    /**
     * Removes a share for a specific calendar of a specific user.
     *
     * @param Request $request     The HTTP POST request
     * @param string  $username    The username of the user whose calendar share is to be removed
     * @param string  $calendar_id The ID of the calendar whose share is to be removed
     *
     * @return JsonResponse A JSON response indicating the success or failure of the operation
     */
    #[Route('/calendars/{username}/share/{calendar_id}/remove', name: 'calendars_share_remove', methods: ['POST'], requirements: ['calendar_id' => "\d+", 'username' => "[a-zA-Z0-9_-]+"])]
    public function removeUserCalendarsShare(Request $request, string $username, int $calendar_id, ManagerRegistry $doctrine): JsonResponse
    {
        if (!$this->validateApiKey($request)) {
            return $this->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }
        
        $ownerInstance = $doctrine->getRepository(CalendarInstance::class)->findOneBy([
            'id' => $calendar_id,
            'principalUri' => Principal::PREFIX.$username,
        ]);

        if (!$ownerInstance) {
            return $this->json(['status' => 'error', 'message' => 'Invalid Calendar ID/Username'], 400);
        }

        $userId = $request->get('user_id');
        if (!is_numeric($userId)) {
            return $this->json(['status' => 'error', 'message' => 'Invalid Sharee ID'], 400);
        }

        $instance = $doctrine->getRepository(CalendarInstance::class)->findOneById($calendar_id);
        $shareeToRemove = $doctrine->getRepository(Principal::class)->findOneById($userId);

        if (!$instance || !$shareeToRemove) {
            return $this->json(['status' => 'error', 'message' => 'Calendar Instance/User Not Found'], 404);
        }

        $existingSharedInstance = $doctrine->getRepository(CalendarInstance::class)->findSharedInstanceOfInstanceFor($instance->getCalendar()->getId(), $shareeToRemove->getUri());

        if ($existingSharedInstance) {
            $entityManager = $doctrine->getManager();
            $entityManager->remove($existingSharedInstance);
            $entityManager->flush();
        }

        return $this->json(['status' => 'success'], 200);
    }
}
