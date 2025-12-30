<?php

namespace App\Author\Domain\Model;

use DateTimeImmutable;

class Author
{
    public function __construct(
        private string $id,
        private string $name,
        private DateTimeImmutable $createdAt
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updateName(string $name): void
    {
        $this->name = $name;
    }
}