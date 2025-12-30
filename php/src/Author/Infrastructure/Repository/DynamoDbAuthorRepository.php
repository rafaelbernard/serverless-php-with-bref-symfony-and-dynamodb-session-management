<?php

namespace App\Author\Infrastructure\Repository;

use App\Author\Domain\Model\Author;
use App\Author\Domain\Repository\AuthorRepositoryInterface;
use AsyncAws\DynamoDb\DynamoDbClient;
use AsyncAws\DynamoDb\Input\DeleteItemInput;
use AsyncAws\DynamoDb\Input\GetItemInput;
use AsyncAws\DynamoDb\Input\PutItemInput;
use AsyncAws\DynamoDb\Input\ScanInput;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use DateTimeImmutable;

class DynamoDbAuthorRepository implements AuthorRepositoryInterface
{
    public function __construct(
        private DynamoDbClient $dynamoDb,
        private string $tableName
    ) {}

    public function save(Author $author): void
    {
        $this->dynamoDb->putItem(new PutItemInput([
            'TableName' => $this->tableName,
            'Item' => [
                'PK' => new AttributeValue(['S' => 'AUTHOR#' . $author->getId()]),
                'SK' => new AttributeValue(['S' => 'METADATA']),
                'id' => new AttributeValue(['S' => $author->getId()]),
                'name' => new AttributeValue(['S' => $author->getName()]),
                'createdAt' => new AttributeValue(['S' => $author->getCreatedAt()->format('Y-m-d\TH:i:s\Z')]),
            ]
        ]));
    }

    public function findById(string $id): ?Author
    {
        $result = $this->dynamoDb->getItem(new GetItemInput([
            'TableName' => $this->tableName,
            'Key' => [
                'PK' => new AttributeValue(['S' => 'AUTHOR#' . $id]),
                'SK' => new AttributeValue(['S' => 'METADATA']),
            ]
        ]));

        $item = $result->getItem();
        if (!$item) {
            return null;
        }

        return new Author(
            $item['id']->getS(),
            $item['name']->getS(),
            new DateTimeImmutable($item['createdAt']->getS())
        );
    }

    public function findAll(): array
    {
        $result = $this->dynamoDb->scan(new ScanInput([
            'TableName' => $this->tableName,
            'FilterExpression' => 'begins_with(PK, :pk)',
            'ExpressionAttributeValues' => [
                ':pk' => new AttributeValue(['S' => 'AUTHOR#'])
            ]
        ]));

        return array_map([$this, 'mapItemToAuthor'], iterator_to_array($result->getItems()));
    }

    public function delete(string $id): void
    {
        $this->dynamoDb->deleteItem(new DeleteItemInput([
            'TableName' => $this->tableName,
            'Key' => [
                'PK' => new AttributeValue(['S' => 'AUTHOR#' . $id]),
                'SK' => new AttributeValue(['S' => 'METADATA']),
            ]
        ]));
    }

    public function getAuthorWithBookCount(): array
    {
        // Get all authors
        $authorsResult = $this->dynamoDb->scan(new ScanInput([
            'TableName' => $this->tableName,
            'FilterExpression' => 'begins_with(PK, :pk)',
            'ExpressionAttributeValues' => [
                ':pk' => new AttributeValue(['S' => 'AUTHOR#'])
            ]
        ]));

        // Get all books
        $booksResult = $this->dynamoDb->scan(new ScanInput([
            'TableName' => $this->tableName,
            'FilterExpression' => 'begins_with(PK, :pk)',
            'ExpressionAttributeValues' => [
                ':pk' => new AttributeValue(['S' => 'BOOK#'])
            ]
        ]));

        // Count books by author
        $bookCounts = [];
        foreach (iterator_to_array($booksResult->getItems()) as $item) {
            $authorName = $item['author']->getS();
            $bookCounts[$authorName] = ($bookCounts[$authorName] ?? 0) + 1;
        }

        // Map authors with book counts
        $stats = [];
        foreach (iterator_to_array($authorsResult->getItems()) as $item) {
            $authorName = $item['name']->getS();
            $stats[] = [
                'author' => $this->mapItemToAuthor($item),
                'bookCount' => $bookCounts[$authorName] ?? 0
            ];
        }

        return $stats;
    }

    private function mapItemToAuthor(array $item): Author
    {
        return new Author(
            $item['id']->getS(),
            $item['name']->getS(),
            new DateTimeImmutable($item['createdAt']->getS())
        );
    }
}