<?php

namespace App\Security\Csrf;

use AsyncAws\DynamoDb\DynamoDbClient;
use AsyncAws\DynamoDb\Input\DeleteItemInput;
use AsyncAws\DynamoDb\Input\GetItemInput;
use AsyncAws\DynamoDb\Input\PutItemInput;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use Symfony\Component\Security\Csrf\TokenStorage\ClearableTokenStorageInterface;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;

class DynamoDbCsrfTokenStorage implements TokenStorageInterface, ClearableTokenStorageInterface
{
    private const string TOKEN_PREFIX = 'CSRF#';
    private const int DEFAULT_TTL_SECONDS = 360; // 1 hour

    public function __construct(
        private readonly DynamoDbClient $dynamoDb,
        private readonly string $tableName,
        private readonly int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
    ) {}

    public function getToken(string $tokenId): string
    {
        $result = $this->dynamoDb->getItem(new GetItemInput([
            'TableName' => $this->tableName,
            'Key' => [
                'PK' => new AttributeValue(['S' => 'CSRF-TOKEN']),
                'SK' => new AttributeValue(['S' => self::TOKEN_PREFIX . $tokenId]),
            ]
        ]));

        $item = $result->getItem();
        if (!$item || !isset($item['token'])) {
            return '';
        }

        return $item['token']->getS();
    }

    public function setToken(string $tokenId, string $token): void
    {
        $expiresAt = time() + $this->ttlSeconds;

        $this->dynamoDb->putItem(new PutItemInput([
            'TableName' => $this->tableName,
            'Item' => [
                'PK' => new AttributeValue(['S' => 'CSRF-TOKEN']),
                'SK' => new AttributeValue(['S' => self::TOKEN_PREFIX . $tokenId]),
                'token' => new AttributeValue(['S' => $token]),
                'expiresAt' => new AttributeValue(['N' => (string)$expiresAt]),
            ]
        ]));
    }

    public function removeToken(string $tokenId): ?string
    {
        $token = $this->getToken($tokenId);
        
        if ($token) {
            $this->dynamoDb->deleteItem(new DeleteItemInput([
                'TableName' => $this->tableName,
                'Key' => [
                    'PK' => new AttributeValue(['S' => 'CSRF-TOKEN']),
                    'SK' => new AttributeValue(['S' => self::TOKEN_PREFIX . $tokenId]),
                ]
            ]));
        }

        return $token ?: null;
    }

    public function hasToken(string $tokenId): bool
    {
        return !empty($this->getToken($tokenId));
    }

    public function clear(): void
    {
        // Rely on DynamoDB TTL to expire items; nothing to scan/delete here.
        return;
    }
}
