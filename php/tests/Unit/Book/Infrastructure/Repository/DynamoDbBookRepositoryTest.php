<?php

namespace App\Tests\Unit\Book\Infrastructure\Repository;

use App\Book\Domain\Model\Book;
use App\Book\Infrastructure\Repository\DynamoDbBookRepository;
use AsyncAws\DynamoDb\DynamoDbClient;
use AsyncAws\DynamoDb\Result\GetItemOutput;
use AsyncAws\DynamoDb\Result\ScanOutput;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class DynamoDbBookRepositoryTest extends TestCase
{
    private DynamoDbClient $dynamoDb;
    private DynamoDbBookRepository $repository;

    protected function setUp(): void
    {
        $this->dynamoDb = $this->createMock(DynamoDbClient::class);
        $this->repository = new DynamoDbBookRepository($this->dynamoDb, 'test-table');
    }

    public function testSave(): void
    {
        $book = new Book('test-id', 'Test Title', 'Test Author', new DateTimeImmutable('2023-01-01'));

        $this->dynamoDb->expects($this->once())
            ->method('putItem')
            ->with($this->callback(function ($input) {
                return $input->getTableName() === 'test-table';
            }));

        $this->repository->save($book);
    }

    public function testFindByIdReturnsBook(): void
    {
        $mockOutput = $this->createMock(GetItemOutput::class);
        $mockOutput->method('getItem')->willReturn([
            'id' => new AttributeValue(['S' => 'test-id']),
            'title' => new AttributeValue(['S' => 'Test Title']),
            'author' => new AttributeValue(['S' => 'Test Author']),
            'createdAt' => new AttributeValue(['S' => '2023-01-01T00:00:00Z'])
        ]);

        $this->dynamoDb->method('getItem')->willReturn($mockOutput);

        $book = $this->repository->findById('test-id');

        $this->assertInstanceOf(Book::class, $book);
        $this->assertEquals('test-id', $book->getId());
        $this->assertEquals('Test Title', $book->getTitle());
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $mockOutput = $this->createMock(GetItemOutput::class);
        $mockOutput->method('getItem')->willReturn([]);

        $this->dynamoDb->method('getItem')->willReturn($mockOutput);

        $book = $this->repository->findById('non-existent');

        $this->assertNull($book);
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