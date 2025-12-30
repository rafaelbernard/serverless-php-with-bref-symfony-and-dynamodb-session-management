<?php

namespace App\Tests\Unit\Book\Domain\Model;

use App\Book\Domain\Model\Book;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class BookTest extends TestCase
{
    public function testBookCreation(): void
    {
        $id = 'test-id';
        $title = 'Test Book';
        $author = 'Test Author';
        $createdAt = new DateTimeImmutable();

        $book = new Book($id, $title, $author, $createdAt);

        $this->assertEquals($id, $book->getId());
        $this->assertEquals($title, $book->getTitle());
        $this->assertEquals($author, $book->getAuthor());
        $this->assertEquals($createdAt, $book->getCreatedAt());
    }

    public function testUpdateTitle(): void
    {
        $book = new Book('id', 'Original Title', 'Author', new DateTimeImmutable());
        $newTitle = 'Updated Title';

        $book->updateTitle($newTitle);

        $this->assertEquals($newTitle, $book->getTitle());
    }

    public function testUpdateAuthor(): void
    {
        $book = new Book('id', 'Title', 'Original Author', new DateTimeImmutable());
        $newAuthor = 'Updated Author';

        $book->updateAuthor($newAuthor);

        $this->assertEquals($newAuthor, $book->getAuthor());
    }
}