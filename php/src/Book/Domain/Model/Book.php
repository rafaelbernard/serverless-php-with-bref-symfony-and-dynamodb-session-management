<?php

namespace App\Book\Domain\Model;

use DateTimeImmutable;

class Book
{
    public function __construct(
        private string $id,
        private string $author,
        private string $title,
        private DateTimeImmutable $createdAt
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updateTitle(string $title): void
    {
        $this->title = $title;
    }

    public function updateAuthor(string $author): void
    {
        $this->author = $author;
    }
}
