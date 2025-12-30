<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

class SessionTestController extends AbstractController
{
    #[Route('/session-test', name: 'app_session_test', methods: ['GET'])]
    public function sessionTest(Request $request, SessionInterface $session): Response
    {
        // Touch the session to ensure it's started and persisted via the configured handler
        $count = (int) $session->get('counter', 0);
        $count++;
        $session->set('counter', $count);

        return new JsonResponse([
            'message' => 'Session incremented',
            'session_id' => $session->getId(),
            'counter' => $count,
            'handler' => 'App\\Session\\DynamoDbSessionHandler',
        ]);
    }

    #[Route('/session-test/reset', name: 'app_session_test_reset', methods: ['POST'])]
    public function sessionReset(SessionInterface $session): Response
    {
        $session->clear();

        return new JsonResponse([
            'message' => 'Session cleared',
            'session_id' => $session->getId(),
        ]);
    }
}
