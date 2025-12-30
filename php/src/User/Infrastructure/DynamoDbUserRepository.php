<?php

namespace App\User\Infrastructure;

use App\User\Domain\User;
use App\User\Domain\UserRepositoryInterface;
use AsyncAws\DynamoDb\DynamoDbClient;
use AsyncAws\DynamoDb\Input\GetItemInput;
use AsyncAws\DynamoDb\Input\PutItemInput;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;

final class DynamoDbUserRepository implements UserRepositoryInterface
{
    private const string PK_VALUE = 'USER';
    private const string SK_PREFIX = 'EMAIL#';

    public function __construct(
        private readonly DynamoDbClient $dynamoDb,
        private readonly string $tableName,
    ) {}

    public function findByEmail(string $email): ?User
    {
        $start = microtime(true);
        error_log("USER REPO FIND START: $email");
        
        $result = $this->dynamoDb->getItem(new GetItemInput([
            'TableName' => $this->tableName,
            'Key' => [
                'PK' => new AttributeValue(['S' => self::PK_VALUE]),
                'SK' => new AttributeValue(['S' => self::SK_PREFIX . strtolower($email)]),
            ],
            'ConsistentRead' => true,
        ]));

        $elapsed = round((microtime(true) - $start) * 1000, 2);
        error_log("USER REPO DYNAMO: {$elapsed}ms");

        $item = $result->getItem();
        if (!$item) {
            error_log("USER REPO NOT FOUND: {$elapsed}ms");
            return null;
        }

        $emailAttr = $item['email']->getS() ?? null;
        $hash = $item['passwordHash']->getS() ?? null;
        if ($emailAttr === null || $hash === null) {
            error_log("USER REPO INVALID DATA: {$elapsed}ms");
            return null;
        }

        $totalElapsed = round((microtime(true) - $start) * 1000, 2);
        error_log("USER REPO SUCCESS: {$totalElapsed}ms");
        
        return new User($emailAttr, $hash);
    }

    public function create(string $email, string $passwordHash): User
    {
        $normalizedEmail = strtolower($email);

        // Conditional put to avoid overwriting existing users
        $this->dynamoDb->putItem(new PutItemInput([
            'TableName' => $this->tableName,
            'Item' => [
                'PK' => new AttributeValue(['S' => self::PK_VALUE]),
                'SK' => new AttributeValue(['S' => self::SK_PREFIX . $normalizedEmail]),
                'email' => new AttributeValue(['S' => $normalizedEmail]),
                'passwordHash' => new AttributeValue(['S' => $passwordHash]),
                'createdAt' => new AttributeValue(['N' => (string) time()]),
            ],
            'ConditionExpression' => 'attribute_not_exists(#pk) AND attribute_not_exists(#sk)',
            'ExpressionAttributeNames' => [
                '#pk' => 'PK',
                '#sk' => 'SK',
            ],
        ]));

        return new User($normalizedEmail, $passwordHash);
    }
}
