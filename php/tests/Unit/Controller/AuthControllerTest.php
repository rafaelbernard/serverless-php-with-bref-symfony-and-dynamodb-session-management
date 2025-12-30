<?php

namespace App\Tests\Unit\Controller;

use App\Controller\AuthController;
use App\User\Domain\User;
use App\User\Domain\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Twig\Environment;

class AuthControllerTest extends TestCase
{
    private UserRepositoryInterface $userRepository;
    private CsrfTokenManagerInterface $csrfTokenManager;
    private AuthController $controller;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);

        $this->controller = new AuthController(
            $this->userRepository,
            $this->csrfTokenManager
        );

        // Mock Twig environment
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('rendered content');
        $this->controller->setContainer($this->createMockContainer($twig));
    }

    public function testRegisterGetRequestRendersForm(): void
    {
        $request = new Request();
        $session = $this->createMock(SessionInterface::class);

        $csrfToken = $this->createMock(CsrfToken::class);
        $csrfToken->method('getValue')->willReturn('test-csrf-token');
        $this->csrfTokenManager->method('getToken')->willReturn($csrfToken);

        $response = $this->controller->register($request, $session);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testRegisterPostWithInvalidCsrfToken(): void
    {
        $request = new Request([], [
            '_csrf_token' => 'invalid-token',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirm' => 'password123',
        ]);
        $request->setMethod('POST');

        $session = $this->createMock(SessionInterface::class);

        $this->csrfTokenManager
            ->method('isTokenValid')
            ->willReturn(false);

        $csrfToken = $this->createMock(CsrfToken::class);
        $csrfToken->method('getValue')->willReturn('test-csrf-token');
        $this->csrfTokenManager->method('getToken')->willReturn($csrfToken);

        $response = $this->controller->register($request, $session);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testRegisterPostWithInvalidEmail(): void
    {
        $request = new Request([], [
            '_csrf_token' => 'valid-token',
            'email' => 'invalid-email',
            'password' => 'password123',
            'password_confirm' => 'password123',
        ]);
        $request->setMethod('POST');

        $session = $this->createMock(SessionInterface::class);

        $this->csrfTokenManager
            ->method('isTokenValid')
            ->willReturn(true);

        $csrfToken = $this->createMock(CsrfToken::class);
        $csrfToken->method('getValue')->willReturn('test-csrf-token');
        $this->csrfTokenManager->method('getToken')->willReturn($csrfToken);

        $response = $this->controller->register($request, $session);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testRegisterPostWithShortPassword(): void
    {
        $request = new Request([], [
            '_csrf_token' => 'valid-token',
            'email' => 'test@example.com',
            'password' => '123',
            'password_confirm' => '123',
        ]);
        $request->setMethod('POST');

        $session = $this->createMock(SessionInterface::class);

        $this->csrfTokenManager
            ->method('isTokenValid')
            ->willReturn(true);

        $csrfToken = $this->createMock(CsrfToken::class);
        $csrfToken->method('getValue')->willReturn('test-csrf-token');
        $this->csrfTokenManager->method('getToken')->willReturn($csrfToken);

        $response = $this->controller->register($request, $session);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testRegisterPostWithMismatchedPasswords(): void
    {
        $request = new Request([], [
            '_csrf_token' => 'valid-token',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirm' => 'different123',
        ]);
        $request->setMethod('POST');

        $session = $this->createMock(SessionInterface::class);

        $this->csrfTokenManager
            ->method('isTokenValid')
            ->willReturn(true);

        $csrfToken = $this->createMock(CsrfToken::class);
        $csrfToken->method('getValue')->willReturn('test-csrf-token');
        $this->csrfTokenManager->method('getToken')->willReturn($csrfToken);

        $response = $this->controller->register($request, $session);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testRegisterPostWithExistingEmail(): void
    {
        $request = new Request([], [
            '_csrf_token' => 'valid-token',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirm' => 'password123',
        ]);
        $request->setMethod('POST');

        $session = $this->createMock(SessionInterface::class);

        $this->csrfTokenManager
            ->method('isTokenValid')
            ->willReturn(true);

        $existingUser = $this->createMock(User::class);
        $this->userRepository
            ->method('findByEmail')
            ->with('existing@example.com')
            ->willReturn($existingUser);

        $csrfToken = $this->createMock(CsrfToken::class);
        $csrfToken->method('getValue')->willReturn('test-csrf-token');
        $this->csrfTokenManager->method('getToken')->willReturn($csrfToken);

        $response = $this->controller->register($request, $session);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testRegisterPostSuccessRedirectsToLogin(): void
    {
        $request = new Request([], [
            '_csrf_token' => 'valid-token',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirm' => 'password123',
        ]);
        $request->setMethod('POST');

        $session = $this->createMock(SessionInterface::class);

        $this->csrfTokenManager
            ->method('isTokenValid')
            ->willReturn(true);

        $this->userRepository
            ->method('findByEmail')
            ->with('newuser@example.com')
            ->willReturn(null);

        $this->userRepository
            ->expects($this->once())
            ->method('create')
            ->with(
                'newuser@example.com',
                $this->callback(function ($hash) {
                    // Verify it's a valid password hash
                    return is_string($hash) && strlen($hash) > 0;
                })
            );

        $response = $this->controller->register($request, $session);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/login', $response->getTargetUrl());
    }

    public function testLoginGetRequestRendersForm(): void
    {
        $authUtils = $this->createMock(AuthenticationUtils::class);
        $authUtils->method('getLastAuthenticationError')->willReturn(null);
        $authUtils->method('getLastUsername')->willReturn('');

        $csrfToken = $this->createMock(CsrfToken::class);
        $csrfToken->method('getValue')->willReturn('test-csrf-token');
        $this->csrfTokenManager->method('getToken')->willReturn($csrfToken);

        $response = $this->controller->login($authUtils);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testLoginWithAuthenticationError(): void
    {
        $authUtils = $this->createMock(AuthenticationUtils::class);
        $authError = new \Exception('Invalid credentials');
        $authUtils->method('getLastAuthenticationError')->willReturn($authError);
        $authUtils->method('getLastUsername')->willReturn('test@example.com');

        $csrfToken = $this->createMock(CsrfToken::class);
        $csrfToken->method('getValue')->willReturn('test-csrf-token');
        $this->csrfTokenManager->method('getToken')->willReturn($csrfToken);

        $response = $this->controller->login($authUtils);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testLoginSuccessRendersSuccessPage(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->willReturn('test@example.com');
        $session->method('getId')->willReturn('session-id-123');

        $response = $this->controller->loginSuccess($session);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testLogoutThrowsLogicException(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('This method can be blank');

        $this->controller->logout();
    }

    private function createMockContainer($twig)
    {
        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['twig', true],
            ['router', true],
            ['security.csrf.token_manager', true]
        ]);
        
        $router = $this->createMock(\Symfony\Component\Routing\RouterInterface::class);
        $router->method('generate')->willReturnCallback(function ($name, $params = []) {
            return '/' . str_replace('app_', '', $name);
        });

        $container->method('get')->willReturnMap([
            ['twig', $twig],
            ['router', $router],
            ['security.csrf.token_manager', $this->csrfTokenManager]
        ]);
        return $container;
    }
}
