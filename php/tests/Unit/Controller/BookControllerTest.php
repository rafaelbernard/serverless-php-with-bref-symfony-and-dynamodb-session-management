<?php

namespace App\Tests\Unit\Controller;

use App\Author\Domain\Repository\AuthorRepositoryInterface;
use App\Book\Domain\Model\Book;
use App\Book\Domain\Repository\BookRepositoryInterface;
use App\Controller\BookController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Twig\Environment;

class BookControllerTest extends TestCase
{
    private BookRepositoryInterface $bookRepository;
    private AuthorRepositoryInterface $authorRepository;
    private CsrfTokenManagerInterface $csrfTokenManager;
    private BookController $controller;

    protected function setUp(): void
    {
        $this->bookRepository = $this->createMock(BookRepositoryInterface::class);
        $this->authorRepository = $this->createMock(AuthorRepositoryInterface::class);
        $this->csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        
        $this->controller = new BookController(
            $this->bookRepository,
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
        $this->bookRepository->method('findAll')->willReturn([]);

        $response = $this->controller->index();

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testNewGetRequest(): void
    {
        $request = new Request();
        $this->authorRepository->method('findAll')->willReturn([]);

        $response = $this->controller->new($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testShowReturnsBook(): void
    {
        $book = new Book('test-id', 'Test Title', 'Test Author', new \DateTimeImmutable());
        $this->bookRepository->method('findById')->willReturn($book);

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