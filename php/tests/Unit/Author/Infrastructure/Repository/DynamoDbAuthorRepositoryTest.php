<?php

namespace App\Tests\Unit\Author\Infrastructure\Repository;

use App\Author\Domain\Model\Author;
use App\Author\Infrastructure\Repository\DynamoDbAuthorRepository;
use AsyncAws\DynamoDb\DynamoDbClient;
use AsyncAws\DynamoDb\Result\GetItemOutput;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class DynamoDbAuthorRepositoryTest extends TestCase
{
    private DynamoDbClient $dynamoDb;
    private DynamoDbAuthorRepository $repository;

    protected function setUp(): void
    {
        $this->dynamoDb = $this->createMock(DynamoDbClient::class);
        $this->repository = new DynamoDbAuthorRepository($this->dynamoDb, 'test-table');
    }

    public function testSave(): void
    {
        $author = new Author('test-id', 'Test Author', new DateTimeImmutable('2023-01-01'));

        $this->dynamoDb->expects($this->once())
            ->method('putItem')
            ->with($this->callback(function ($input) {
                return $input->getTableName() === 'test-table';
            }));

        $this->repository->save($author);
    }

    public function testFindByIdReturnsAuthor(): void
    {
        $mockOutput = $this->createMock(GetItemOutput::class);
        $mockOutput->method('getItem')->willReturn([
            'id' => new AttributeValue(['S' => 'test-id']),
            'name' => new AttributeValue(['S' => 'Test Author']),
            'createdAt' => new AttributeValue(['S' => '2023-01-01T00:00:00Z'])
        ]);

        $this->dynamoDb->method('getItem')->willReturn($mockOutput);

        $author = $this->repository->findById('test-id');

        $this->assertInstanceOf(Author::class, $author);
        $this->assertEquals('test-id', $author->getId());
        $this->assertEquals('Test Author', $author->getName());
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $mockOutput = $this->createMock(GetItemOutput::class);
        $mockOutput->method('getItem')->willReturn([]);

        $this->dynamoDb->method('getItem')->willReturn($mockOutput);

        $author = $this->repository->findById('non-existent');

        $this->assertNull($author);
    }

    public function testDelete(): void
    {
        $this->dynamoDb->expects($this->once())
            ->method('deleteItem')
            ->with($this->callback(function ($input) {
                return $input->getTableName() === 'test-table';
            }));

        $this->repository->delete('test-id');
    }
}