<?php

namespace App\Book\Domain\Repository;

use App\Book\Domain\Model\Book;

interface BookRepositoryInterface
{
    public function save(Book $book): void;
    public function findById(string $id): ?Book;
    public function findAll(): array;
    public function findLastFive(): array;
    public function delete(string $id): void;
    public function getAuthorStats(): array;
}