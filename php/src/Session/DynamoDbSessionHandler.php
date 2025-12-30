<?php

namespace App\Session;

use AsyncAws\DynamoDb\DynamoDbClient;
use AsyncAws\DynamoDb\Input\DeleteItemInput;
use AsyncAws\DynamoDb\Input\GetItemInput;
use AsyncAws\DynamoDb\Input\PutItemInput;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;

/**
 * A minimal DynamoDB-backed PHP session handler using AsyncAws.
 *
 * Table design (single-table compatible):
 *  - PK: "SESSION"
 *  - SK: "SID#<session_id>"
 *  - data: base64-encoded session payload (string)
 *  - expiresAt: unix epoch seconds (number), enable DynamoDB TTL on this attribute
 *
 * Garbage collection is handled by DynamoDB's TTL, so gc() is a no-op.
 */
class DynamoDbSessionHandler implements \SessionHandlerInterface
{
    private const string PK_VALUE = 'SESSION';
    private const string SK_PREFIX = 'SID#';

    public function __construct(
        private readonly DynamoDbClient $dynamoDb,
        private readonly string $tableName,
        private readonly int $ttlSeconds = 3600,
    ) {}

    public function open(string $path, string $name): bool
    {
        // Nothing to do
        return true;
    }

    public function close(): bool
    {
        // Nothing to do
        return true;
    }

    public function read(string $id): string
    {
        $start = microtime(true);
        error_log("SESSION READ START: $id");
        
        $result = $this->dynamoDb->getItem(new GetItemInput([
            'TableName' => $this->tableName,
            'Key' => [
                'PK' => new AttributeValue(['S' => self::PK_VALUE]),
                'SK' => new AttributeValue(['S' => self::SK_PREFIX . $id]),
            ],
            // Strongly consistent read to reduce stale sessions
            'ConsistentRead' => true,
        ]));
        
        $elapsed = round((microtime(true) - $start) * 1000, 2);
        error_log("SESSION READ DYNAMO: {$elapsed}ms");

        $item = $result->getItem();
        if (!$item || !isset($item['data'])) {
            error_log("SESSION READ EMPTY: {$elapsed}ms");
            return '';
        }

        $encoded = $item['data']->getS();
        if ($encoded === null) {
            error_log("SESSION READ NULL: {$elapsed}ms");
            return '';
        }

        $payload = base64_decode($encoded, true);
        $result = $payload === false ? '' : $payload;
        
        $totalElapsed = round((microtime(true) - $start) * 1000, 2);
        error_log("SESSION READ COMPLETE: {$totalElapsed}ms, size: " . strlen($result));
        
        return $result;
    }

    public function write(string $id, string $data): bool
    {
        $start = microtime(true);
        error_log("SESSION WRITE START: $id, size: " . strlen($data));
        
        $expiresAt = time() + $this->ttlSeconds;

        $this->dynamoDb->putItem(new PutItemInput([
            'TableName' => $this->tableName,
            'Item' => [
                'PK' => new AttributeValue(['S' => self::PK_VALUE]),
                'SK' => new AttributeValue(['S' => self::SK_PREFIX . $id]),
                'data' => new AttributeValue(['S' => base64_encode($data)]),
                'expiresAt' => new AttributeValue(['N' => (string) $expiresAt]),
            ],
        ]));

        $elapsed = round((microtime(true) - $start) * 1000, 2);
        error_log("SESSION WRITE COMPLETE: {$elapsed}ms");
        
        return true;
    }

    public function destroy(string $id): bool
    {
        $this->dynamoDb->deleteItem(new DeleteItemInput([
            'TableName' => $this->tableName,
            'Key' => [
                'PK' => new AttributeValue(['S' => self::PK_VALUE]),
                'SK' => new AttributeValue(['S' => self::SK_PREFIX . $id]),
            ],
        ]));

        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        // Rely on DynamoDB TTL to expire items; nothing to scan/delete here.
        return 0;
    }
}
