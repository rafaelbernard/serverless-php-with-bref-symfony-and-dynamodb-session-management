<?php

namespace App\Controller;

use App\Author\Domain\Repository\AuthorRepositoryInterface;
use App\Book\Domain\Model\Book;
use App\Book\Domain\Repository\BookRepositoryInterface;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/books')]
class BookController extends AbstractController
{
    public function __construct(
        private readonly BookRepositoryInterface $bookRepository,
        private readonly AuthorRepositoryInterface $authorRepository,
    ) {}

    #[Route('/', name: 'book_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('book/index.html.twig', [
            'books' => $this->bookRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'book_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('book_new', $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }
            
            $book = new Book(
                uniqid(),
                $request->request->get('author'),
                $request->request->get('title'),
                new DateTimeImmutable()
            );
            
            $this->bookRepository->save($book);
            
            return $this->redirectToRoute('book_index');
        }

        return $this->render('book/new.html.twig', [
            'authors' => $this->authorRepository->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'book_show', methods: ['GET'])]
    public function show(string $id): Response
    {
        $book = $this->bookRepository->findById($id);
        
        if (!$book) {
            throw $this->createNotFoundException();
        }

        return $this->render('book/show.html.twig', [
            'book' => $book,
        ]);
    }

    #[Route('/{id}', name: 'book_delete', methods: ['POST'])]
    public function delete(Request $request, string $id): Response
    {
        if (!$this->isCsrfTokenValid('book_delete', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        
        $this->bookRepository->delete($id);
        
        return $this->redirectToRoute('book_index');
    }
}
