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

#[Route('/api/v1', name: 'api_v1_')]
class ApiController extends AbstractController
{
    /**
     * Validates the provided username.
     *
     * @param string $username The username to validate
     *
     * @return bool True if the username is valid, false otherwise
     */
    private function validateUsername(string $username): bool
    {
        return !empty($username) && is_string($username) && !preg_match('/[^a-zA-Z0-9_-]/', $username);
    }

    /**
     * Gets the current timestamp in ISO 8601 format.
     *
     * @return string The current timestamp
     */
    private function getTimestamp(): string
    {
        return date('c');
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
        return $this->json(['status' => 'OK', 'timestamp' => $this->getTimestamp()], 200);
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
            'data' => $users,
            'timestamp' => $this->getTimestamp(),
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
    #[Route('/users/{username}', name: 'user_detail', methods: ['GET'], requirements: ['username' => '[a-zA-Z0-9_-]+'])]
    public function getUserDetails(Request $request, ManagerRegistry $doctrine, string $username): JsonResponse
    {
        $user = $doctrine->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username);

        if (!$user) {
            return $this->json(['status' => 'error', 'message' => 'User Not Found', 'timestamp' => $this->getTimestamp()], 404);
        }

        $data = [
            'principal_id' => $user->getId(),
            'uri' => $user->getUri(),
            'username' => $user->getUsername(),
            'displayname' => $user->getDisplayName(),
            'email' => $user->getEmail(),
        ];

        $response = [
            'status' => 'success',
            'data' => $data,
            'timestamp' => $this->getTimestamp(),
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
    #[Route('/calendars/{username}', name: 'calendars', methods: ['GET'], requirements: ['username' => '[a-zA-Z0-9_-]+'])]
    public function getUserCalendars(Request $request, string $username, ManagerRegistry $doctrine): JsonResponse
    {
        if (!$doctrine->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username)) {
            return $this->json(['status' => 'error', 'message' => 'User Not Found', 'timestamp' => $this->getTimestamp()], 404);
        }

        $allCalendars = $doctrine->getRepository(CalendarInstance::class)->findByPrincipalUri(Principal::PREFIX.$username);
        $allSubscriptions = $doctrine->getRepository(CalendarSubscription::class)->findByPrincipalUri(Principal::PREFIX.$username);

        $calendars = [];
        $sharedCalendars = [];
        foreach ($allCalendars as $calendar) {
            $objectCounts = $doctrine->getRepository(CalendarInstance::class)->getObjectCountsByComponentType($calendar->getCalendar()->getId());
            $calendarData = [
                'id' => $calendar->getId(),
                'uri' => $calendar->getUri(),
                'displayname' => $calendar->getDisplayName(),
                'description' => $calendar->getDescription(),
                'events' => $objectCounts['events'],
                'notes' => $objectCounts['notes'],
                'tasks' => $objectCounts['tasks'],
            ];
            if (!$calendar->isShared()) {
                $calendars[] = $calendarData;
            } else {
                $sharedCalendars[] = $calendarData;
            }
        }

        $subscriptions = [];
        foreach ($allSubscriptions as $subscription) {
            $objectCounts = $doctrine->getRepository(CalendarInstance::class)->getObjectCountsByComponentType($subscription->getCalendar()->getId());
            $subscriptions[] = [
                'id' => $subscription->getId(),
                'uri' => $subscription->getUri(),
                'displayname' => $subscription->getDisplayName(),
                'description' => $subscription->getDescription(),
                'events' => $objectCounts['events'],
                'notes' => $objectCounts['notes'],
                'tasks' => $objectCounts['tasks'],
            ];
        }

        $response = [
            'status' => 'success',
            'data' => [
                'user_calendars' => $calendars,
                'shared_calendars' => $sharedCalendars,
                'subscriptions' => $subscriptions,
            ],
            'timestamp' => $this->getTimestamp(),
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
    #[Route('/calendars/{username}/{calendar_id}', name: 'calendar_details', methods: ['GET'], requirements: ['calendar_id' => "\d+", 'username' => '[a-zA-Z0-9_-]+'])]
    public function getUserCalendarDetails(Request $request, string $username, int $calendar_id, ManagerRegistry $doctrine): JsonResponse
    {
        if (!$doctrine->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username)) {
            return $this->json(['status' => 'error', 'message' => 'User Not Found', 'timestamp' => $this->getTimestamp()], 404);
        }

        $allCalendars = $doctrine->getRepository(CalendarInstance::class)->findByPrincipalUri(Principal::PREFIX.$username);

        $calendar_details = [];
        foreach ($allCalendars as $calendar) {
            if (!$calendar->isShared() && $calendar->getId() === $calendar_id) {
                $objectCounts = $doctrine->getRepository(CalendarInstance::class)->getObjectCountsByComponentType($calendar->getCalendar()->getId());
                $calendar_details = [
                    'id' => $calendar->getId(),
                    'uri' => $calendar->getUri(),
                    'displayname' => $calendar->getDisplayName(),
                    'description' => $calendar->getDescription(),
                    'events' => [
                        'enabled' => $calendar->getCalendar()->isComponentEnabled(Calendar::COMPONENT_EVENTS),
                        'count' => $objectCounts['events'],
                    ],
                    'notes' => [
                        'enabled' => $calendar->getCalendar()->isComponentEnabled(Calendar::COMPONENT_NOTES),
                        'count' => $objectCounts['notes'],
                    ],
                    'tasks' => [
                        'enabled' => $calendar->getCalendar()->isComponentEnabled(Calendar::COMPONENT_TODOS),
                        'count' => $objectCounts['tasks'],
                    ],
                ];
            }
        }

        $response = [
            'status' => 'success',
            'data' => $calendar_details,
            'timestamp' => $this->getTimestamp(),
        ];

        return $this->json($response, 200);
    }

    /**
     * Creates a new calendar for a specific user.
     *
     * @param Request $request  The HTTP POST request
     * @param string  $username The username of the user for whom the calendar is to be created
     *
     * @return JsonResponse A JSON response indicating the success or failure of the operation
     */
    #[Route('/calendars/{username}/create', name: 'calendar_create', methods: ['POST'], requirements: ['username' => '[a-zA-Z0-9_-]+'])]
    public function createNewUserCalendar(Request $request, string $username, ManagerRegistry $doctrine): JsonResponse
    {
        if (!$doctrine->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username)) {
            return $this->json(['status' => 'error', 'message' => 'User Not Found', 'timestamp' => $this->getTimestamp()], 404);
        }

        // Parse JSON body
        $data = json_decode($request->getContent(), true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            return $this->json(['status' => 'error', 'message' => 'Invalid JSON', 'timestamp' => $this->getTimestamp()], 400);
        }

        $calendarName = $data['name'] ?? null;
        if (empty($calendarName) || 1 !== preg_match('/^[a-zA-Z0-9 ._-]{1,64}$/', $calendarName)) {
            return $this->json(['status' => 'error', 'message' => 'Invalid Calendar Name', 'timestamp' => $this->getTimestamp()], 400);
        }
        $calendarURI = $data['uri'] ?? null;
        if (empty($calendarURI) || 1 !== preg_match('/^[a-z0-9_-]{1,128}$/', $calendarURI)) {
            return $this->json(['status' => 'error', 'message' => 'Invalid Calendar URI', 'timestamp' => $this->getTimestamp()], 400);
        }

        $uriCheck = $doctrine->getRepository(CalendarInstance::class)->findOneBy([
            'principalUri' => Principal::PREFIX.$username,
            'uri' => $calendarURI,
        ]);
        if ($uriCheck) {
            return $this->json(['status' => 'error', 'message' => 'Calendar URI Already Exists', 'timestamp' => $this->getTimestamp()], 400);
        }

        $calendarDescription = $data['description'] ?? '';
        if (!empty($calendarDescription) && 1 !== preg_match('/^[a-zA-Z0-9 ._-]{1,256}$/', $calendarDescription)) {
            return $this->json(['status' => 'error', 'message' => 'Invalid Calendar Description', 'timestamp' => $this->getTimestamp()], 400);
        }

        $entityManager = $doctrine->getManager();
        $calendarInstance = new CalendarInstance();
        $calendar = new Calendar();
        $calendarInstance->setCalendar($calendar);

        $calendarComponents = [];
        // Handle both boolean and string values
        $eventsSupport = $data['events_support'] ?? true;
        if (true === $eventsSupport || 'true' === $eventsSupport) {
            $calendarComponents[] = Calendar::COMPONENT_EVENTS;
        }
        $notesSupport = $data['notes_support'] ?? false;
        if (true === $notesSupport || 'true' === $notesSupport) {
            $calendarComponents[] = Calendar::COMPONENT_NOTES;
        }
        $tasksSupport = $data['tasks_support'] ?? false;
        if (true === $tasksSupport || 'true' === $tasksSupport) {
            $calendarComponents[] = Calendar::COMPONENT_TODOS;
        }
        $calendarInstance->getCalendar()->setComponents(implode(',', $calendarComponents));

        try {
            $calendarInstance
            ->setCalendar($calendar)
            ->setAccess(CalendarInstance::ACCESS_SHAREDOWNER)
            ->setDescription($calendarDescription)
            ->setDisplayName($calendarName)
            ->setUri($calendarURI)
            ->setPrincipalUri(Principal::PREFIX.$username);

            $entityManager->persist($calendarInstance);
            $entityManager->flush();
        } catch (\Exception $e) {
            return $this->json(['status' => 'error', 'message' => 'Failed to Create Calendar', 'timestamp' => $this->getTimestamp()], 500);
        }

        $response = [
            'status' => 'success',
            'data' => [
                'calendar_id' => $calendarInstance->getId(),
                'calendar_uri' => $calendarInstance->getUri(),
            ],
            'timestamp' => $this->getTimestamp(),
        ];

        return $this->json($response, 200);
    }

    /**
     * Edits an existing calendar for a specific user.
     *
     * @param Request $request     The HTTP POST request
     * @param string  $username    The username of the user whose calendar is to be edited
     * @param int     $calendar_id The ID of the calendar to be edited
     *
     * @return JsonResponse A JSON response indicating the success or failure of the operation
     */
    #[Route('/calendars/{username}/{calendar_id}/edit', name: 'calendar_edit', methods: ['POST'], requirements: ['calendar_id' => "\d+", 'username' => '[a-zA-Z0-9_-]+'])]
    public function editUserCalendar(Request $request, string $username, int $calendar_id, ManagerRegistry $doctrine): JsonResponse
    {
        if (!$doctrine->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username)) {
            return $this->json(['status' => 'error', 'message' => 'User Not Found', 'timestamp' => $this->getTimestamp()], 404);
        }

        $ownerInstance = $doctrine->getRepository(CalendarInstance::class)->findOneBy([
            'id' => $calendar_id,
            'principalUri' => Principal::PREFIX.$username,
        ]);

        if (!$ownerInstance) {
            return $this->json(['status' => 'error', 'message' => 'Invalid Calendar ID', 'timestamp' => $this->getTimestamp()], 400);
        }

        $calendarInstance = $doctrine->getRepository(CalendarInstance::class)->findOneById($calendar_id);
        if (!$calendarInstance) {
            return $this->json(['status' => 'error', 'message' => 'Calendar Instance Not Found', 'timestamp' => $this->getTimestamp()], 404);
        }

        // Parse JSON body
        $data = json_decode($request->getContent(), true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            return $this->json(['status' => 'error', 'message' => 'Invalid JSON', 'timestamp' => $this->getTimestamp()], 400);
        }

        $calendarName = $data['name'] ?? null;
        if (empty($calendarName) || 1 !== preg_match('/^[a-zA-Z0-9 ._-]{1,64}$/', $calendarName)) {
            return $this->json(['status' => 'error', 'message' => 'Invalid Calendar Name', 'timestamp' => $this->getTimestamp()], 400);
        }

        $calendarDescription = $data['description'] ?? '';
        if (!empty($calendarDescription) && 1 !== preg_match('/^[a-zA-Z0-9 ._-]{1,256}$/', $calendarDescription)) {
            return $this->json(['status' => 'error', 'message' => 'Invalid Calendar Description', 'timestamp' => $this->getTimestamp()], 400);
        }

        $entityManager = $doctrine->getManager();
        $calendarInstance->setDisplayName($calendarName);
        $calendarInstance->setDescription($calendarDescription);

        $calendarComponents = [];
        // Handle both boolean and string values
        $eventsSupport = $data['events_support'] ?? true;
        if (true === $eventsSupport || 'true' === $eventsSupport) {
            $calendarComponents[] = Calendar::COMPONENT_EVENTS;
        }
        $notesSupport = $data['notes_support'] ?? false;
        if (true === $notesSupport || 'true' === $notesSupport) {
            $calendarComponents[] = Calendar::COMPONENT_NOTES;
        }
        $tasksSupport = $data['tasks_support'] ?? false;
        if (true === $tasksSupport || 'true' === $tasksSupport) {
            $calendarComponents[] = Calendar::COMPONENT_TODOS;
        }
        $calendarInstance->getCalendar()->setComponents(implode(',', $calendarComponents));

        try {
            $entityManager->persist($calendarInstance);
            $entityManager->flush();
        } catch (\Exception $e) {
            return $this->json(['status' => 'error', 'message' => 'Failed to Edit Calendar', 'timestamp' => $this->getTimestamp()], 500);
        }

        return $this->json(['status' => 'success', 'timestamp' => $this->getTimestamp()], 200);
    }

    /**
     * Retrieves a list of shares for a specific calendar of a specific user (id, username, displayname, email, write_access).
     *
     * @param Request $request     The HTTP GET request
     * @param string  $username    The username of the user whose calendar shares are to be retrieved
     * @param int     $calendar_id The ID of the calendar whose shares are to be retrieved
     *
     * @return JsonResponse A JSON response containing the list of calendar shares
     */
    #[Route('/calendars/{username}/shares/{calendar_id}', name: 'calendars_shares', methods: ['GET'], requirements: ['calendar_id' => "\d+", 'username' => '[a-zA-Z0-9_-]+'])]
    public function getUserCalendarsShares(Request $request, string $username, int $calendar_id, ManagerRegistry $doctrine): JsonResponse
    {
        if (!$doctrine->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username)) {
            return $this->json(['status' => 'error', 'message' => 'User Not Found', 'timestamp' => $this->getTimestamp()], 404);
        }

        $ownerInstance = $doctrine->getRepository(CalendarInstance::class)->findOneBy([
            'id' => $calendar_id,
            'principalUri' => Principal::PREFIX.$username,
        ]);

        if (!$ownerInstance) {
            return $this->json(['status' => 'error', 'message' => 'Invalid Calendar ID/Username', 'timestamp' => $this->getTimestamp()], 400);
        }

        $instances = $doctrine->getRepository(CalendarInstance::class)->findSharedInstancesOfInstance($calendar_id, true);

        $calendars = [];
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
            'data' => $calendars,
            'timestamp' => $this->getTimestamp(),
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
    #[Route('/calendars/{username}/share/{calendar_id}/add', name: 'calendars_share', methods: ['POST'], requirements: ['calendar_id' => "\d+", 'username' => '[a-zA-Z0-9_-]+'])]
    public function setUserCalendarsShare(Request $request, string $username, int $calendar_id, ManagerRegistry $doctrine): JsonResponse
    {
        if (!$doctrine->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username)) {
            return $this->json(['status' => 'error', 'message' => 'User Not Found', 'timestamp' => $this->getTimestamp()], 404);
        }

        $ownerInstance = $doctrine->getRepository(CalendarInstance::class)->findOneBy([
            'id' => $calendar_id,
            'principalUri' => Principal::PREFIX.$username,
        ]);

        if (!$ownerInstance) {
            return $this->json(['status' => 'error', 'message' => 'Invalid Calendar ID and User ID', 'timestamp' => $this->getTimestamp()], 400);
        }

        // Parse JSON body
        $data = json_decode($request->getContent(), true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            return $this->json(['status' => 'error', 'message' => 'Invalid JSON', 'timestamp' => $this->getTimestamp()], 400);
        }

        $shareeUsername = $data['username'] ?? null;
        $writeAccess = $data['write_access'] ?? null;
        if (!$this->validateUsername($shareeUsername) || !in_array($writeAccess, [true, false, 'true', 'false'], true)) {
            return $this->json(['status' => 'error', 'message' => 'Invalid Sharee ID/Write Access Value', 'timestamp' => $this->getTimestamp()], 400);
        }

        $instance = $doctrine->getRepository(CalendarInstance::class)->findOneById($calendar_id);
        $newShareeToAdd = $doctrine->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$shareeUsername);

        if (!$instance || !$newShareeToAdd) {
            return $this->json(['status' => 'error', 'message' => 'Calendar Instance/User Not Found', 'timestamp' => $this->getTimestamp()], 404);
        }

        $existingSharedInstance = $doctrine->getRepository(CalendarInstance::class)->findSharedInstanceOfInstanceFor($instance->getCalendar()->getId(), $newShareeToAdd->getUri());
        $accessLevel = (true === $writeAccess || 'true' === $writeAccess ? CalendarInstance::ACCESS_READWRITE : CalendarInstance::ACCESS_READ);
        $entityManager = $doctrine->getManager();

        try {
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
        } catch (\Exception $e) {
            return $this->json(['status' => 'error', 'message' => 'Failed to Edit Calendar', 'timestamp' => $this->getTimestamp()], 500);
        }

        return $this->json(['status' => 'success', 'timestamp' => $this->getTimestamp()], 200);
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
    #[Route('/calendars/{username}/share/{calendar_id}/remove', name: 'calendars_share_remove', methods: ['POST'], requirements: ['calendar_id' => "\d+", 'username' => '[a-zA-Z0-9_-]+'])]
    public function removeUserCalendarsShare(Request $request, string $username, int $calendar_id, ManagerRegistry $doctrine): JsonResponse
    {
        if (!$doctrine->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$username)) {
            return $this->json(['status' => 'error', 'message' => 'User Not Found', 'timestamp' => $this->getTimestamp()], 404);
        }

        $ownerInstance = $doctrine->getRepository(CalendarInstance::class)->findOneBy([
            'id' => $calendar_id,
            'principalUri' => Principal::PREFIX.$username,
        ]);

        if (!$ownerInstance) {
            return $this->json(['status' => 'error', 'message' => 'Invalid Calendar ID', 'timestamp' => $this->getTimestamp()], 400);
        }

        // Parse JSON body
        $data = json_decode($request->getContent(), true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            return $this->json(['status' => 'error', 'message' => 'Invalid JSON', 'timestamp' => $this->getTimestamp()], 400);
        }

        $shareeUsername = $data['username'] ?? null;
        if (!$this->validateUsername($shareeUsername)) {
            return $this->json(['status' => 'error', 'message' => 'Invalid Username', 'timestamp' => $this->getTimestamp()], 400);
        }

        $instance = $doctrine->getRepository(CalendarInstance::class)->findOneById($calendar_id);
        $shareeToRemove = $doctrine->getRepository(Principal::class)->findOneByUri(Principal::PREFIX.$shareeUsername);

        if (!$instance || !$shareeToRemove) {
            return $this->json(['status' => 'error', 'message' => 'Calendar Instance/User Not Found', 'timestamp' => $this->getTimestamp()], 404);
        }

        try {
            $existingSharedInstance = $doctrine->getRepository(CalendarInstance::class)->findSharedInstanceOfInstanceFor($instance->getCalendar()->getId(), $shareeToRemove->getUri());

            if ($existingSharedInstance) {
                $entityManager = $doctrine->getManager();
                $entityManager->remove($existingSharedInstance);
                $entityManager->flush();
            }
        } catch (\Exception $e) {
            return $this->json(['status' => 'error', 'message' => 'Failed to Remove Share', 'timestamp' => $this->getTimestamp()], 500);
        }

        return $this->json(['status' => 'success', 'timestamp' => $this->getTimestamp()], 200);
    }
}
