<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class DebugController extends AbstractController
{
    #[Route('/debug/session', name: 'debug_session', methods: ['GET'])]
    public function debugSession(Request $request, SessionInterface $session, TokenStorageInterface $tokenStorage): JsonResponse
    {
        $start = microtime(true);
        $timings = [];
        
        $timings['start'] = 0;
        
        $token = $tokenStorage->getToken();
        $timings['token_retrieved'] = round((microtime(true) - $start) * 1000, 2);
        
        $user = $token?->getUser();
        $timings['user_retrieved'] = round((microtime(true) - $start) * 1000, 2);
        
        $sessionData = $session->all();
        $timings['session_data'] = round((microtime(true) - $start) * 1000, 2);
        
        $response = [
            'execution_time_ms' => round((microtime(true) - $start) * 1000, 2),
            'timings' => $timings,
            'session_id' => $session->getId(),
            'session_started' => $session->isStarted(),
            'session_data' => $sessionData,
            'user_authenticated' => $user !== null,
            'user_identifier' => $user?->getUserIdentifier(),
            'user_roles' => $user?->getRoles() ?? [],
            'token_class' => $token ? get_class($token) : null,
            'environment' => [
                'APP_ENV' => $_ENV['APP_ENV'] ?? 'not set',
                'BOOK_TABLE_NAME' => $_ENV['BOOK_TABLE_NAME'] ?? 'not set',
            ]
        ];
        
        $timings['response_built'] = round((microtime(true) - $start) * 1000, 2);
        $response['timings'] = $timings;
        
        return new JsonResponse($response);
    }
    
    #[Route('/debug/ping', name: 'debug_ping', methods: ['GET'])]
    public function ping(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok', 'timestamp' => time()]);
    }
}
