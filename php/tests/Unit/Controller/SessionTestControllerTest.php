<?php

namespace App\Tests\Unit\Controller;

use App\Controller\SessionTestController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SessionTestControllerTest extends TestCase
{
    private SessionTestController $controller;
    private SessionInterface $session;

    protected function setUp(): void
    {
        $this->session = $this->createMock(SessionInterface::class);
        $this->controller = new SessionTestController();
    }

    public function testSessionTestIncrementsCounter(): void
    {
        $request = new Request();

        $this->session
            ->expects($this->once())
            ->method('get')
            ->with('counter', 0)
            ->willReturn(0);

        $this->session
            ->expects($this->once())
            ->method('set')
            ->with('counter', 1);

        $this->session
            ->method('getId')
            ->willReturn('test-session-id');

        $response = $this->controller->sessionTest($request, $this->session);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Session incremented', $data['message']);
        $this->assertEquals('test-session-id', $data['session_id']);
        $this->assertEquals(1, $data['counter']);
        $this->assertEquals('App\\Session\\DynamoDbSessionHandler', $data['handler']);
    }

    public function testSessionTestIncrementsExistingCounter(): void
    {
        $request = new Request();

        $this->session
            ->expects($this->once())
            ->method('get')
            ->with('counter', 0)
            ->willReturn(5);

        $this->session
            ->expects($this->once())
            ->method('set')
            ->with('counter', 6);

        $this->session
            ->method('getId')
            ->willReturn('test-session-id');

        $response = $this->controller->sessionTest($request, $this->session);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(6, $data['counter']);
    }

    public function testSessionResetClearsSession(): void
    {
        $this->session
            ->expects($this->once())
            ->method('clear');

        $this->session
            ->method('getId')
            ->willReturn('test-session-id');

        $response = $this->controller->sessionReset($this->session);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Session cleared', $data['message']);
        $this->assertEquals('test-session-id', $data['session_id']);
    }

    public function testSessionTestReturnsJsonFormat(): void
    {
        $request = new Request();

        $this->session->method('get')->willReturn(0);
        $this->session->method('getId')->willReturn('test-id');

        $response = $this->controller->sessionTest($request, $this->session);

        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
        
        // Verify it's valid JSON
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('session_id', $data);
        $this->assertArrayHasKey('counter', $data);
        $this->assertArrayHasKey('handler', $data);
    }

    public function testSessionResetReturnsJsonFormat(): void
    {
        $this->session->method('getId')->willReturn('test-id');

        $response = $this->controller->sessionReset($this->session);

        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
        
        // Verify it's valid JSON
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('session_id', $data);
    }
}
