<?php

namespace App\Tests\Unit\User\Infrastructure;

use App\User\Domain\User;
use App\User\Infrastructure\DynamoDbUserRepository;
use AsyncAws\DynamoDb\DynamoDbClient;
use AsyncAws\DynamoDb\Input\GetItemInput;
use AsyncAws\DynamoDb\Input\PutItemInput;
use AsyncAws\DynamoDb\Result\GetItemOutput;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use PHPUnit\Framework\TestCase;

class DynamoDbUserRepositoryTest extends TestCase
{
    private DynamoDbClient $dynamoDb;
    private DynamoDbUserRepository $repository;
    private string $tableName = 'test-table';

    protected function setUp(): void
    {
        $this->dynamoDb = $this->createMock(DynamoDbClient::class);
        $this->repository = new DynamoDbUserRepository($this->dynamoDb, $this->tableName);
    }

    public function testFindByEmailReturnsUserWhenFound(): void
    {
        $email = 'test@example.com';
        $passwordHash = 'hashed_password';

        $emailAttr = $this->createMock(AttributeValue::class);
        $emailAttr->method('getS')->willReturn(strtolower($email));

        $hashAttr = $this->createMock(AttributeValue::class);
        $hashAttr->method('getS')->willReturn($passwordHash);

        $getItemOutput = $this->createMock(GetItemOutput::class);
        $getItemOutput->method('getItem')->willReturn([
            'email' => $emailAttr,
            'passwordHash' => $hashAttr,
        ]);

        $this->dynamoDb
            ->expects($this->once())
            ->method('getItem')
            ->with($this->callback(function (GetItemInput $input) use ($email) {
                $keys = $input->getKey();
                return $input->getTableName() === $this->tableName
                    && $input->getConsistentRead() === true
                    && $keys['PK']->getS() === 'USER'
                    && $keys['SK']->getS() === 'EMAIL#' . strtolower($email);
            }))
            ->willReturn($getItemOutput);

        $user = $this->repository->findByEmail($email);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals(strtolower($email), $user->email);
        $this->assertEquals($passwordHash, $user->passwordHash);
    }

    public function testFindByEmailReturnsNullWhenNotFound(): void
    {
        $getItemOutput = $this->createMock(GetItemOutput::class);
        $getItemOutput->method('getItem')->willReturn([]);

        $this->dynamoDb
            ->expects($this->once())
            ->method('getItem')
            ->willReturn($getItemOutput);

        $user = $this->repository->findByEmail('notfound@example.com');

        $this->assertNull($user);
    }

    public function testFindByEmailNormalizesEmailToLowercase(): void
    {
        $email = 'Test@EXAMPLE.COM';

        $getItemOutput = $this->createMock(GetItemOutput::class);
        $getItemOutput->method('getItem')->willReturn([]);

        $this->dynamoDb
            ->expects($this->once())
            ->method('getItem')
            ->with($this->callback(function (GetItemInput $input) {
                $keys = $input->getKey();
                // Verify SK uses lowercase email
                return $keys['SK']->getS() === 'EMAIL#test@example.com';
            }))
            ->willReturn($getItemOutput);

        $this->repository->findByEmail($email);
    }

    public function testFindByEmailReturnsNullWhenEmailAttributeIsMissing(): void
    {
        $hashAttr = $this->createMock(AttributeValue::class);
        $hashAttr->method('getS')->willReturn('hash');

        $getItemOutput = $this->createMock(GetItemOutput::class);
        $getItemOutput->method('getItem')->willReturn([
            'passwordHash' => $hashAttr,
            // email attribute missing
        ]);

        $this->dynamoDb
            ->method('getItem')
            ->willReturn($getItemOutput);

        $user = $this->repository->findByEmail('test@example.com');

        $this->assertNull($user);
    }

    public function testFindByEmailReturnsNullWhenPasswordHashAttributeIsMissing(): void
    {
        $emailAttr = $this->createMock(AttributeValue::class);
        $emailAttr->method('getS')->willReturn('test@example.com');

        $getItemOutput = $this->createMock(GetItemOutput::class);
        $getItemOutput->method('getItem')->willReturn([
            'email' => $emailAttr,
            // passwordHash attribute missing
        ]);

        $this->dynamoDb
            ->method('getItem')
            ->willReturn($getItemOutput);

        $user = $this->repository->findByEmail('test@example.com');

        $this->assertNull($user);
    }

    public function testCreateStoresUserInDynamoDB(): void
    {
        $email = 'newuser@example.com';
        $passwordHash = 'hashed_password';

        $this->dynamoDb
            ->expects($this->once())
            ->method('putItem')
            ->with($this->callback(function (PutItemInput $input) use ($email, $passwordHash) {
                $items = $input->getItem();

                // Verify table name
                if ($input->getTableName() !== $this->tableName) {
                    return false;
                }

                // Verify PK
                if (!isset($items['PK']) || $items['PK']->getS() !== 'USER') {
                    return false;
                }

                // Verify SK with normalized email
                if (!isset($items['SK']) || $items['SK']->getS() !== 'EMAIL#' . strtolower($email)) {
                    return false;
                }

                // Verify email attribute
                if (!isset($items['email']) || $items['email']->getS() !== strtolower($email)) {
                    return false;
                }

                // Verify passwordHash
                if (!isset($items['passwordHash']) || $items['passwordHash']->getS() !== $passwordHash) {
                    return false;
                }

                // Verify createdAt timestamp
                if (!isset($items['createdAt']) || !is_numeric($items['createdAt']->getN())) {
                    return false;
                }

                // Verify condition expression to prevent overwrites
                if ($input->getConditionExpression() !== 'attribute_not_exists(#pk) AND attribute_not_exists(#sk)') {
                    return false;
                }

                return true;
            }));

        $user = $this->repository->create($email, $passwordHash);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals(strtolower($email), $user->email);
        $this->assertEquals($passwordHash, $user->passwordHash);
    }

    public function testCreateNormalizesEmailToLowercase(): void
    {
        $email = 'NewUser@EXAMPLE.COM';
        $passwordHash = 'hash';

        $this->dynamoDb
            ->expects($this->once())
            ->method('putItem')
            ->with($this->callback(function (PutItemInput $input) {
                $items = $input->getItem();
                return $items['email']->getS() === 'newuser@example.com'
                    && $items['SK']->getS() === 'EMAIL#newuser@example.com';
            }));

        $user = $this->repository->create($email, $passwordHash);

        $this->assertEquals('newuser@example.com', $user->email);
    }

    public function testCreateUsesConditionExpressionToPreventOverwrite(): void
    {
        $this->dynamoDb
            ->expects($this->once())
            ->method('putItem')
            ->with($this->callback(function (PutItemInput $input) {
                // Verify conditional expression exists
                $condition = $input->getConditionExpression();
                return $condition === 'attribute_not_exists(#pk) AND attribute_not_exists(#sk)';
            }));

        $this->repository->create('test@example.com', 'hash');
    }

    public function testFindByEmailUsesConsistentRead(): void
    {
        $getItemOutput = $this->createMock(GetItemOutput::class);
        $getItemOutput->method('getItem')->willReturn([]);

        $this->dynamoDb
            ->expects($this->once())
            ->method('getItem')
            ->with($this->callback(function (GetItemInput $input) {
                // Verify ConsistentRead is true
                return $input->getConsistentRead() === true;
            }))
            ->willReturn($getItemOutput);

        $this->repository->findByEmail('test@example.com');
    }
}
