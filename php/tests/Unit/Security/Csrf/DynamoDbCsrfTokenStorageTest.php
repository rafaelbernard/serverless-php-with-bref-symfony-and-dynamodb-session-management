<?php

namespace App\Tests\Unit\Security\Csrf;

use App\Security\Csrf\DynamoDbCsrfTokenStorage;
use AsyncAws\DynamoDb\DynamoDbClient;
use AsyncAws\DynamoDb\Result\GetItemOutput;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use PHPUnit\Framework\TestCase;

class DynamoDbCsrfTokenStorageTest extends TestCase
{
    private DynamoDbClient $dynamoDb;
    private DynamoDbCsrfTokenStorage $storage;

    protected function setUp(): void
    {
        $this->dynamoDb = $this->createMock(DynamoDbClient::class);
        $this->storage = new DynamoDbCsrfTokenStorage($this->dynamoDb, 'test-table');
    }

    public function testSetToken(): void
    {
        $this->dynamoDb->expects($this->once())
            ->method('putItem')
            ->with($this->callback(function ($input) {
                return $input->getTableName() === 'test-table';
            }));

        $this->storage->setToken('test-token-id', 'test-token-value');
    }

    public function testGetTokenReturnsToken(): void
    {
        $mockOutput = $this->createMock(GetItemOutput::class);
        $mockOutput->method('getItem')->willReturn([
            'token' => new AttributeValue(['S' => 'test-token-value'])
        ]);

        $this->dynamoDb->method('getItem')->willReturn($mockOutput);

        $token = $this->storage->getToken('test-token-id');

        $this->assertEquals('test-token-value', $token);
    }

    public function testGetTokenReturnsEmptyWhenNotFound(): void
    {
        $mockOutput = $this->createMock(GetItemOutput::class);
        $mockOutput->method('getItem')->willReturn([]);

        $this->dynamoDb->method('getItem')->willReturn($mockOutput);

        $token = $this->storage->getToken('non-existent');

        $this->assertEquals('', $token);
    }

    public function testHasTokenReturnsTrueWhenExists(): void
    {
        $mockOutput = $this->createMock(GetItemOutput::class);
        $mockOutput->method('getItem')->willReturn([
            'token' => new AttributeValue(['S' => 'test-token-value'])
        ]);

        $this->dynamoDb->method('getItem')->willReturn($mockOutput);

        $hasToken = $this->storage->hasToken('test-token-id');

        $this->assertTrue($hasToken);
    }

    public function testHasTokenReturnsFalseWhenNotExists(): void
    {
        $mockOutput = $this->createMock(GetItemOutput::class);
        $mockOutput->method('getItem')->willReturn([]);

        $this->dynamoDb->method('getItem')->willReturn($mockOutput);

        $hasToken = $this->storage->hasToken('non-existent');

        $this->assertFalse($hasToken);
    }

    public function testRemoveToken(): void
    {
        $mockOutput = $this->createMock(GetItemOutput::class);
        $mockOutput->method('getItem')->willReturn([
            'token' => new AttributeValue(['S' => 'test-token-value'])
        ]);

        $this->dynamoDb->method('getItem')->willReturn($mockOutput);
        $this->dynamoDb->expects($this->once())->method('deleteItem');

        $removedToken = $this->storage->removeToken('test-token-id');

        $this->assertEquals('test-token-value', $removedToken);
    }
}