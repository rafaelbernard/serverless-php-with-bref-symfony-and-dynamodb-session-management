<?php

namespace App\Controller;

use App\Author\Domain\Model\Author;
use App\Author\Domain\Repository\AuthorRepositoryInterface;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/authors')]
class AuthorController extends AbstractController
{
    public function __construct(
        private readonly AuthorRepositoryInterface $authorRepository,
    ) {}

    #[Route('/', name: 'author_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('author/index.html.twig', [
            'authors' => $this->authorRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'author_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('author_new', $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }
            
            $author = new Author(
                Uuid::uuid4()->toString(),
                $request->request->get('name'),
                new DateTimeImmutable()
            );
            
            $this->authorRepository->save($author);
            
            return $this->redirectToRoute('author_index');
        }

        return $this->render('author/new.html.twig');
    }

    #[Route('/{id}', name: 'author_show', methods: ['GET'])]
    public function show(string $id): Response
    {
        $author = $this->authorRepository->findById($id);
        
        if (!$author) {
            throw $this->createNotFoundException();
        }

        return $this->render('author/show.html.twig', [
            'author' => $author,
        ]);
    }

    #[Route('/{id}', name: 'author_delete', methods: ['POST'])]
    public function delete(Request $request, string $id): Response
    {
        if (!$this->isCsrfTokenValid('author_delete', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        
        $this->authorRepository->delete($id);
        
        return $this->redirectToRoute('author_index');
    }
}
