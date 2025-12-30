<?php

namespace App\Tests\Unit\Author\Domain\Model;

use App\Author\Domain\Model\Author;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class AuthorTest extends TestCase
{
    public function testAuthorCreation(): void
    {
        $id = 'test-id';
        $name = 'Test Author';
        $createdAt = new DateTimeImmutable();

        $author = new Author($id, $name, $createdAt);

        $this->assertEquals($id, $author->getId());
        $this->assertEquals($name, $author->getName());
        $this->assertEquals($createdAt, $author->getCreatedAt());
    }

    public function testUpdateName(): void
    {
        $author = new Author('id', 'Original Name', new DateTimeImmutable());
        $newName = 'Updated Name';

        $author->updateName($newName);

        $this->assertEquals($newName, $author->getName());
    }
}