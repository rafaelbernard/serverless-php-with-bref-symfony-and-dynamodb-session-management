<?php

namespace App\Author\Domain\Repository;

use App\Author\Domain\Model\Author;

interface AuthorRepositoryInterface
{
    public function save(Author $author): void;
    public function findById(string $id): ?Author;
    public function findAll(): array;
    public function delete(string $id): void;
    public function getAuthorWithBookCount(): array;
}