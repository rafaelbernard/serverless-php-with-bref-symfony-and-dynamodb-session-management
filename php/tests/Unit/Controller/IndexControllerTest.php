<?php

namespace App\Tests\Unit\Controller;

use App\Book\Domain\Repository\BookRepositoryInterface;
use App\Controller\IndexController;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

class IndexControllerTest extends TestCase
{
    private BookRepositoryInterface $bookRepository;
    private IndexController $controller;

    protected function setUp(): void
    {
        $this->bookRepository = $this->createMock(BookRepositoryInterface::class);
        
        $this->controller = new IndexController($this->bookRepository);

        // Mock Twig environment
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('rendered content');
        $this->controller->setContainer($this->createMockContainer($twig));
    }

    public function testNumberReturnsResponse(): void
    {
        $this->bookRepository->method('getAuthorStats')->willReturn([]);
        $this->bookRepository->method('findLastFive')->willReturn([]);

        $response = $this->controller->number();

        $this->assertEquals(200, $response->getStatusCode());
    }

    private function createMockContainer($twig)
    {
        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($twig);
        return $container;
    }
}