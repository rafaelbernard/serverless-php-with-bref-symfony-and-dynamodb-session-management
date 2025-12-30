<?php

namespace App\Book\Infrastructure\Repository;

use App\Author\Domain\Repository\AuthorRepositoryInterface;
use App\Book\Domain\Model\Book;
use App\Book\Domain\Repository\BookRepositoryInterface;
use AsyncAws\DynamoDb\DynamoDbClient;
use AsyncAws\DynamoDb\Input\DeleteItemInput;
use AsyncAws\DynamoDb\Input\PutItemInput;
use AsyncAws\DynamoDb\Input\QueryInput;
use AsyncAws\DynamoDb\Input\ScanInput;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use DateTimeImmutable;

class DynamoDbBookRepository implements BookRepositoryInterface
{
    public function __construct(
        private DynamoDbClient $dynamoDb,
        private string $tableName,
        private AuthorRepositoryInterface $authorRepository
    ) {}

    public function save(Book $book): void
    {
        $authorId = $this->getAuthorIdByName($book->getAuthor());
        
        $this->dynamoDb->putItem(new PutItemInput([
            'TableName' => $this->tableName,
            'Item' => [
                'PK' => new AttributeValue(['S' => 'BOOK-METADATA']),
                'SK' => new AttributeValue(['S' => 'AUTHOR#' . $authorId . '#BOOK#' . $book->getId()]),
                'id' => new AttributeValue(['S' => $book->getId()]),
                'title' => new AttributeValue(['S' => $book->getTitle()]),
                'author' => new AttributeValue(['S' => $book->getAuthor()]),
                'createdAt' => new AttributeValue(['S' => $book->getCreatedAt()->format('Y-m-d\TH:i:s\Z')]),
            ]
        ]));
    }

    public function findById(string $id): ?Book
    {
        $result = $this->dynamoDb->scan(new ScanInput([
            'TableName' => $this->tableName,
            'FilterExpression' => 'PK = :pk AND contains(SK, :bookId)',
            'ExpressionAttributeValues' => [
                ':pk' => new AttributeValue(['S' => 'BOOK-METADATA']),
                ':bookId' => new AttributeValue(['S' => '#BOOK#' . $id])
            ]
        ]));

        $items = iterator_to_array($result->getItems());
        if (empty($items)) {
            return null;
        }

        return $this->mapItemToBook($items[0]);
    }

    public function findAll(): array
    {
        $result = $this->dynamoDb->query(new QueryInput([
            'TableName' => $this->tableName,
            'KeyConditionExpression' => '#pk = :pk',
            'ExpressionAttributeNames' => [
                '#pk' => 'PK'
            ],
            'ExpressionAttributeValues' => [
                ':pk' => new AttributeValue(['S' => 'BOOK-METADATA'])
            ]
        ]));

        return array_map([$this, 'mapItemToBook'], iterator_to_array($result->getItems()));
    }

    public function findLastFive(): array
    {
        $result = $this->dynamoDb->scan(new ScanInput([
            'TableName' => $this->tableName,
            'FilterExpression' => 'PK = :pk',
            'ExpressionAttributeValues' => [
                ':pk' => new AttributeValue(['S' => 'BOOK-METADATA'])
            ],
            'Limit' => 5
        ]));

        $books = array_map([$this, 'mapItemToBook'], iterator_to_array($result->getItems()));
        usort($books, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());
        
        return array_slice($books, 0, 5);
    }

    public function delete(string $id): void
    {
        // First find the book to get the complete SK
        $book = $this->findById($id);
        if (!$book) {
            return;
        }
        
        $authorId = $this->getAuthorIdByName($book->getAuthor());
        
        $this->dynamoDb->deleteItem(new DeleteItemInput([
            'TableName' => $this->tableName,
            'Key' => [
                'PK' => new AttributeValue(['S' => 'BOOK-METADATA']),
                'SK' => new AttributeValue(['S' => 'AUTHOR#' . $authorId . '#BOOK#' . $id]),
            ]
        ]));
    }

    public function getAuthorStats(): array
    {
        $result = $this->dynamoDb->scan(new ScanInput([
            'TableName' => $this->tableName,
            'FilterExpression' => 'PK = :pk',
            'ExpressionAttributeValues' => [
                ':pk' => new AttributeValue(['S' => 'BOOK-METADATA'])
            ]
        ]));

        $stats = [];
        foreach (iterator_to_array($result->getItems()) as $item) {
            $author = $item['author']->getS();
            $stats[$author] = ($stats[$author] ?? 0) + 1;
        }

        return $stats;
    }

    private function getAuthorIdByName(string $authorName): string
    {
        $authors = $this->authorRepository->findAll();
        foreach ($authors as $author) {
            if ($author->getName() === $authorName) {
                return $author->getId();
            }
        }
        throw new \RuntimeException('Author not found: ' . $authorName);
    }

    /**
     * @throws \DateMalformedStringException
     */
    private function mapItemToBook(array $item): Book
    {
        return new Book(
            $item['id']->getS(),
            $item['title']->getS(),
            $item['author']->getS(),
            new DateTimeImmutable($item['createdAt']->getS())
        );
    }
}
