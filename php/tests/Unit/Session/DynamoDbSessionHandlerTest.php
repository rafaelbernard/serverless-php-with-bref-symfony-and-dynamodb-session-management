<?php

namespace App\Tests\Unit\Session;

use App\Session\DynamoDbSessionHandler;
use AsyncAws\DynamoDb\DynamoDbClient;
use AsyncAws\DynamoDb\Input\DeleteItemInput;
use AsyncAws\DynamoDb\Input\GetItemInput;
use AsyncAws\DynamoDb\Input\PutItemInput;
use AsyncAws\DynamoDb\Result\GetItemOutput;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use PHPUnit\Framework\TestCase;

class DynamoDbSessionHandlerTest extends TestCase
{
    private DynamoDbClient $dynamoDb;
    private DynamoDbSessionHandler $handler;
    private string $tableName = 'test-table';
    private int $ttlSeconds = 3600;

    protected function setUp(): void
    {
        $this->dynamoDb = $this->createMock(DynamoDbClient::class);
        $this->handler = new DynamoDbSessionHandler(
            $this->dynamoDb,
            $this->tableName,
            $this->ttlSeconds
        );
    }

    public function testOpenReturnsTrue(): void
    {
        $result = $this->handler->open('/path', 'session_name');
        $this->assertTrue($result);
    }

    public function testCloseReturnsTrue(): void
    {
        $result = $this->handler->close();
        $this->assertTrue($result);
    }

    public function testReadReturnsEmptyStringWhenSessionNotFound(): void
    {
        $getItemOutput = $this->createMock(GetItemOutput::class);
        $getItemOutput->method('getItem')->willReturn([]);

        $this->dynamoDb
            ->expects($this->once())
            ->method('getItem')
            ->with($this->callback(function (GetItemInput $input) {
                return $input->getTableName() === $this->tableName
                    && $input->getConsistentRead() === true;
            }))
            ->willReturn($getItemOutput);

        $result = $this->handler->read('session-id-123');
        $this->assertSame('', $result);
    }

    public function testReadReturnsDecodedDataWhenSessionExists(): void
    {
        $sessionData = 'test_session_data';
        $encodedData = base64_encode($sessionData);

        $dataAttribute = $this->createMock(AttributeValue::class);
        $dataAttribute->method('getS')->willReturn($encodedData);

        $getItemOutput = $this->createMock(GetItemOutput::class);
        $getItemOutput->method('getItem')->willReturn([
            'data' => $dataAttribute,
        ]);

        $this->dynamoDb
            ->expects($this->once())
            ->method('getItem')
            ->willReturn($getItemOutput);

        $result = $this->handler->read('session-id-123');
        $this->assertSame($sessionData, $result);
    }

    public function testReadReturnsEmptyStringWhenDataAttributeIsNull(): void
    {
        $dataAttribute = $this->createMock(AttributeValue::class);
        $dataAttribute->method('getS')->willReturn(null);

        $getItemOutput = $this->createMock(GetItemOutput::class);
        $getItemOutput->method('getItem')->willReturn([
            'data' => $dataAttribute,
        ]);

        $this->dynamoDb
            ->expects($this->once())
            ->method('getItem')
            ->willReturn($getItemOutput);

        $result = $this->handler->read('session-id-123');
        $this->assertSame('', $result);
    }

    public function testWriteStoresEncodedDataWithTTL(): void
    {
        $sessionId = 'session-id-123';
        $sessionData = 'test_session_data';

        $this->dynamoDb
            ->expects($this->once())
            ->method('putItem')
            ->with($this->callback(function (PutItemInput $input) use ($sessionId, $sessionData) {
                $items = $input->getItem();
                
                // Verify table name
                if ($input->getTableName() !== $this->tableName) {
                    return false;
                }

                // Verify PK
                if (!isset($items['PK']) || $items['PK']->getS() !== 'SESSION') {
                    return false;
                }

                // Verify SK
                if (!isset($items['SK']) || $items['SK']->getS() !== 'SID#' . $sessionId) {
                    return false;
                }

                // Verify data is base64 encoded
                if (!isset($items['data']) || $items['data']->getS() !== base64_encode($sessionData)) {
                    return false;
                }

                // Verify expiresAt is a number
                if (!isset($items['expiresAt']) || !is_numeric($items['expiresAt']->getN())) {
                    return false;
                }

                return true;
            }));

        $result = $this->handler->write($sessionId, $sessionData);
        $this->assertTrue($result);
    }

    public function testWriteCalculatesCorrectTTL(): void
    {
        $sessionId = 'session-id-123';
        $sessionData = 'test_data';
        $beforeWrite = time();

        $this->dynamoDb
            ->expects($this->once())
            ->method('putItem')
            ->with($this->callback(function (PutItemInput $input) use ($beforeWrite) {
                $items = $input->getItem();
                $expiresAt = (int) $items['expiresAt']->getN();
                $expectedMin = $beforeWrite + $this->ttlSeconds;
                $expectedMax = $beforeWrite + $this->ttlSeconds + 2; // Allow 2 seconds tolerance

                return $expiresAt >= $expectedMin && $expiresAt <= $expectedMax;
            }));

        $this->handler->write($sessionId, $sessionData);
    }

    public function testDestroyDeletesSession(): void
    {
        $sessionId = 'session-id-123';

        $this->dynamoDb
            ->expects($this->once())
            ->method('deleteItem')
            ->with($this->callback(function (DeleteItemInput $input) use ($sessionId) {
                $keys = $input->getKey();

                // Verify table name
                if ($input->getTableName() !== $this->tableName) {
                    return false;
                }

                // Verify PK
                if (!isset($keys['PK']) || $keys['PK']->getS() !== 'SESSION') {
                    return false;
                }

                // Verify SK
                if (!isset($keys['SK']) || $keys['SK']->getS() !== 'SID#' . $sessionId) {
                    return false;
                }

                return true;
            }));

        $result = $this->handler->destroy($sessionId);
        $this->assertTrue($result);
    }

    public function testGarbageCollectionReturnsZero(): void
    {
        // DynamoDB TTL handles garbage collection, so gc() should be a no-op
        $result = $this->handler->gc(3600);
        $this->assertSame(0, $result);
    }

    public function testReadUsesConsistentRead(): void
    {
        $getItemOutput = $this->createMock(GetItemOutput::class);
        $getItemOutput->method('getItem')->willReturn([]);

        $this->dynamoDb
            ->expects($this->once())
            ->method('getItem')
            ->with($this->callback(function (GetItemInput $input) {
                // Verify ConsistentRead is set to true
                return $input->getConsistentRead() === true;
            }))
            ->willReturn($getItemOutput);

        $this->handler->read('session-id-123');
    }

    public function testReadHandlesInvalidBase64Gracefully(): void
    {
        // This is an edge case where data might be corrupted
        $dataAttribute = $this->createMock(AttributeValue::class);
        $dataAttribute->method('getS')->willReturn('invalid-base64-!!!');

        $getItemOutput = $this->createMock(GetItemOutput::class);
        $getItemOutput->method('getItem')->willReturn([
            'data' => $dataAttribute,
        ]);

        $this->dynamoDb
            ->expects($this->once())
            ->method('getItem')
            ->willReturn($getItemOutput);

        // Should return empty string when base64_decode fails
        $result = $this->handler->read('session-id-123');
        $this->assertSame('', $result);
    }
}
