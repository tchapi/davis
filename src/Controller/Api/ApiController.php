<?php
namespace App\Controller\Api;

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
     * @return JsonResponse A JSON response containing the list of users or an error message
     */
    #[Route('/users', name: 'users', methods: ['GET'])]
    public function getUsers(Request $request): JsonResponse
    {
        if (!$this->validateApiKey($request)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        // Dummy data for demonstration purposes
        $users = [
            [
                "username" => "johndoe",
                "displayname" => "John Doe",
                "email" => "johndoe@example.com",
                "uri" => "principals/johndoe"
            ]
        ];

        $response = [
            "status" => "success",
            "data" => $users
        ];

        return $this->json($response, 200);
    }

    #[Route('/calendars/{username}', name: 'calendars', methods: ['GET'])]
    public function getUserCalendars(Request $request, string $username): JsonResponse
    {
        if (!$this->validateApiKey($request)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        // Dummy data for demonstration purposes
        $calendars = [
            [
                "displayname" => "Personal Calendar",
                "uri" => "default",
                "description" => "Work related events",
                "events" => 1,
                "notes" => 1,
                "tasks" => 1
            ],
        ];

        $response = [
            "status" => "success",
            "data" => $calendars
        ];

        return $this->json($response, 200);
    }

    #[Route('/addressbooks/{username}', name: 'addressbooks', methods: ['GET'])]
    public function getUserAddressBooks(Request $request, string $username): JsonResponse
    {
        if (!$this->validateApiKey($request)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        // Dummy data for demonstration purposes
        $addressBooks = [
            [
                "displayname" => "Personal Contacts",
                "uri" => "default",
                "description" => "My personal contacts",
                "cards" => 10
            ],
        ];

        $response = [
            "status" => "success",
            "data" => $addressBooks
        ];

        return $this->json($response, 200);
    }
}