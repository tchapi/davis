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

    #[Route('/health', name: 'health', methods: ['GET'])]
    public function healthCheck(Request $request): JsonResponse
    {
        if (!$this->validateApiKey($request)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        return $this->json(['status' => 'OK'], 200);
    }

    /**
     * Retrieves a list of users.
     *
     * @param Request $request The HTTP GET request
     *
     * @return JsonResponse A JSON response containing the list of users,
     */
    #[Route('/users', name: 'users', methods: ['GET'])]
    public function getUsers(Request $request, ManagerRegistry $doctrine): JsonResponse
    {
        if (!$this->validateApiKey($request)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $principals = $doctrine->getRepository(Principal::class)->findByIsMain(true);

        if (!$principals) {
            return $this->json(['status' => 'success', 'data' => []], 200);
        }

        foreach ($principals as $principal) {
            $users[] = [
                'id' => $principal->getId(),
                'uri' => $principal->getUri(),
                'username' => $principal->getUsername(),
                'displayname' => $principal->getDisplayName(),
                'email' => $principal->getEmail(),
            ];
        }

        $response = [
            'status' => 'success',
            'data' => $users,
        ];

        return $this->json($response, 200);
    }

    /**
     * Retrieves details of a specific user.
     *
     * @param Request $request  The HTTP GET request
     * @param string  $username The username of the user whose details are to be retrieved
     *
     * @return JsonResponse A JSON response containing the user details
     */
    #[Route('/users/{username}', name: 'user_detail', methods: ['GET'])]
    public function getUserDetails(Request $request, ManagerRegistry $doctrine, string $username): JsonResponse
    {
        if (!$this->validateApiKey($request)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        if (empty($username) || !is_string($username) || preg_match('/[^a-zA-Z0-9_-]/', $username)) {
            return $this->json(['status' => 'Error', 'message' => 'Invalid Username'], 400);
        }

        $user = $doctrine->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username);

        if (!$user) {
            return $this->json(['status' => 'success', 'data' => []], 200);
        }

        $response = [
            'id' => $user->getId(),
            'uri' => $user->getUri(),
            'username' => $user->getUsername(),
            'displayname' => $user->getDisplayName(),
            'email' => $user->getEmail(),
        ];

        return $this->json($response, 200);
    }

    /**
     * Retrieves a list of calendars for a specific user.
     *
     * @param Request $request  The HTTP GET request
     * @param string  $username The username of the user whose calendars are to be retrieved
     *
     * @return JsonResponse A JSON response containing the list of calendars
     */
    #[Route('/calendars/{username}', name: 'calendars', methods: ['GET'])]
    public function getUserCalendars(Request $request, string $username, ManagerRegistry $doctrine): JsonResponse
    {
        if (!$this->validateApiKey($request)) {
            return $this->json(['status' => 'Error', 'message' => 'Unauthorized'], 401);
        }

        if (empty($username) || !is_string($username) || preg_match('/[^a-zA-Z0-9_-]/', $username)) {
            return $this->json(['status' => 'Error', 'message' => 'Invalid Username'], 400);
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
     * Retrieves details of a specific calendar for a specific user.
     *
     * @param Request $request     The HTTP GET request
     * @param string  $username    The username of the user whose calendar details are to be retrieved
     * @param int     $calendar_id The ID of the calendar whose details are to be retrieved
     *
     * @return JsonResponse A JSON response containing the calendar details
     */
    #[Route('/calendars/{username}/{calendar_id}', name: 'calendar_details', methods: ['GET'])]
    public function getUserCalendarDetails(Request $request, string $username, int $calendar_id, ManagerRegistry $doctrine): JsonResponse
    {
        if (!$this->validateApiKey($request)) {
            return $this->json(['status' => 'Error', 'message' => 'Unauthorized'], 401);
        }

        if (empty($username) || !is_string($username) || preg_match('/[^a-zA-Z0-9_-]/', $username)) {
            return $this->json(['status' => 'Error', 'message' => 'Invalid Username'], 400);
        }

        if (empty($calendar_id) || !is_int($calendar_id)) {
            return $this->json(['status' => 'Error', 'message' => 'Invalid Calendar ID'], 400);
        }

        $allCalendars = $doctrine->getRepository(CalendarInstance::class)->findByPrincipalUri(Principal::PREFIX.$username);

        if (!$allCalendars) {
            return $this->json(['status' => 'success', 'data' => []], 200);
        }

        foreach ($allCalendars as $calendar) {
            if (!$calendar->isShared() && $calendar->getId() === $calendar_id) {
                $calendar = [
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
            'data' => $calendar ?? [],
        ];

        return $this->json($response, 200);
    }

    /**
     * Retrieves a list of shares for a specific calendar of a specific user.
     *
     * @param Request $request     The HTTP GET request
     * @param string  $username    The username of the user whose calendar shares are to be retrieved
     * @param string  $calendar_id The ID of the calendar whose shares are to be retrieved
     *
     * @return JsonResponse A JSON response containing the list of calendar shares
     */
    #[Route('/calendars/{username}/shares/{calendar_id}', name: 'calendars_shares', methods: ['GET'])]
    public function getUserCalendarsShares(Request $request, string $username, int $calendar_id, ManagerRegistry $doctrine): JsonResponse
    {
        if (!$this->validateApiKey($request)) {
            return $this->json(['status' => 'Error', 'message' => 'Unauthorized'], 401);
        }

        if (!is_string($username) || preg_match('/[^a-zA-Z0-9_-]/', $username) || !is_int($calendar_id)) {
            return $this->json(['status' => 'Error', 'message' => 'Invalid Username/Calendar ID'], 400);
        }

        $instances = $doctrine->getRepository(CalendarInstance::class)->findSharedInstancesOfInstance($calendar_id, true);

        if (!$instances) {
            return $this->json(['status' => 'success', 'data' => []], 200);
        }

        foreach ($instances as $instance) {
            $user_id = $doctrine->getRepository(Principal::class)->findOneByUri($instance[0]['principalUri']);

            $calendars[] = [
                'username' => mb_substr($instance[0]['principalUri'], strlen(Principal::PREFIX)),
                'user_id' => $user_id->getId() ?? null,
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
    #[Route('/calendars/{username}/share/{calendar_id}/add', name: 'calendars_share', methods: ['POST'])]
    public function setUserCalendarsShare(Request $request, string $username, string $calendar_id, ManagerRegistry $doctrine): JsonResponse
    {
        if (!$this->validateApiKey($request)) {
            return $this->json(['status' => 'Error', 'message' => 'Unauthorized'], 401);
        }

        if (!is_string($username) || preg_match('/[^a-zA-Z0-9_-]/', $username) || !is_numeric($calendar_id)) {
            return $this->json(['status' => 'Error', 'message' => 'Invalid Username/Calendar ID'], 400);
        }

        if (!is_numeric($request->get('user_id')) || !in_array($request->get('write_access'), ['true', 'false'], true)) {
            return $this->json(['status' => 'Error', 'message' => 'Invalid Sharee ID/Write Access Value'], 400);
        }

        $instance = $doctrine->getRepository(CalendarInstance::class)->findOneById($calendar_id);
        $newShareeToAdd = $doctrine->getRepository(Principal::class)->findOneById($request->get('user_id'));

        if (!$instance || !$newShareeToAdd) {
            return $this->json(['status' => 'Error', 'message' => 'Calendar Instance/User Not Found'], 404);
        }

        $existingSharedInstance = $doctrine->getRepository(CalendarInstance::class)->findSharedInstanceOfInstanceFor($instance->getCalendar()->getId(), $newShareeToAdd->getUri());
        $writeAccess = ('true' === $request->get('write_access') ? CalendarInstance::ACCESS_READWRITE : CalendarInstance::ACCESS_READ);
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
    #[Route('/calendars/{username}/share/{calendar_id}/remove', name: 'calendars_share_remove', methods: ['POST'])]
    public function removeUserCalendarsShare(Request $request, string $username, string $calendar_id, ManagerRegistry $doctrine): JsonResponse
    {
        if (!$this->validateApiKey($request)) {
            return $this->json(['status' => 'Error', 'message' => 'Unauthorized'], 401);
        }

        if (!is_string($username) || preg_match('/[^a-zA-Z0-9_-]/', $username) || !is_numeric($calendar_id)) {
            return $this->json(['status' => 'Error', 'message' => 'Invalid Username/Calendar ID'], 400);
        }

        if (!is_numeric($request->get('user_id'))) {
            return $this->json(['status' => 'Error', 'message' => 'Invalid Sharee ID'], 400);
        }

        $instance = $doctrine->getRepository(CalendarInstance::class)->findOneById($calendar_id);
        $shareeToRemove = $doctrine->getRepository(Principal::class)->findOneById($request->get('user_id'));

        if (!$instance || !$shareeToRemove) {
            return $this->json(['status' => 'Error', 'message' => 'Calendar Instance/User Not Found'], 404);
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
