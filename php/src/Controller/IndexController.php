<?php

namespace App\Controller;

use App\Book\Domain\Repository\BookRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class IndexController extends AbstractController
{
    public function __construct(
        private readonly BookRepositoryInterface $bookRepository
    ) {}

    #[Route('/')]
    public function number(): Response
    {
        return $this->render('index.html.twig', [
            'authorStats' => $this->bookRepository->getAuthorStats(),
            'lastFiveBooks' => $this->bookRepository->findLastFive(),
        ]);
    }
}
