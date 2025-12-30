<?php

namespace App\Tests\Unit\Controller;

use App\Author\Domain\Model\Author;
use App\Author\Domain\Repository\AuthorRepositoryInterface;
use App\Controller\AuthorController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

class AuthorControllerTest extends TestCase
{
    private AuthorRepositoryInterface $authorRepository;
    private CsrfTokenManagerInterface $csrfTokenManager;
    private AuthorController $controller;

    protected function setUp(): void
    {
        $this->authorRepository = $this->createMock(AuthorRepositoryInterface::class);
        $this->csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        
        $this->controller = new AuthorController(
            $this->authorRepository,
            $this->csrfTokenManager
        );

        // Mock Twig environment
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('rendered content');
        $this->controller->setContainer($this->createMockContainer($twig));
    }

    public function testIndexReturnsResponse(): void
    {
        $this->authorRepository->method('getAuthorWithBookCount')->willReturn([]);

        $response = $this->controller->index();

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testNewGetRequest(): void
    {
        $request = new Request();

        $response = $this->controller->new($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testShowReturnsAuthor(): void
    {
        $author = new Author('test-id', 'Test Author', new \DateTimeImmutable());
        $this->authorRepository->method('findById')->willReturn($author);

        $response = $this->controller->show('test-id');

        $this->assertEquals(200, $response->getStatusCode());
    }

    private function createMockContainer($twig)
    {
        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['twig', true],
            ['security.csrf.token_manager', true]
        ]);
        $container->method('get')->willReturnMap([
            ['twig', $twig],
            ['security.csrf.token_manager', $this->csrfTokenManager]
        ]);
        return $container;
    }
}