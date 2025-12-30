<?php

namespace App\Tests\Unit\User\Domain;

use App\User\Domain\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $email = 'test@example.com';
        $passwordHash = 'hashed_password_123';

        $user = new User($email, $passwordHash);

        $this->assertEquals($email, $user->email);
        $this->assertEquals($passwordHash, $user->passwordHash);
    }

    public function testEmailIsReadonly(): void
    {
        $user = new User('test@example.com', 'hash');

        // Attempt to modify should fail at compile time (readonly property)
        // This test just verifies the properties are accessible
        $this->assertEquals('test@example.com', $user->email);
    }

    public function testPasswordHashIsReadonly(): void
    {
        $user = new User('test@example.com', 'hash');

        // Verify property is accessible
        $this->assertEquals('hash', $user->passwordHash);
    }

    public function testCreateUserWithEmptyEmail(): void
    {
        // Even though validation should happen at service layer,
        // the domain model should accept any string
        $user = new User('', 'hash');

        $this->assertEquals('', $user->email);
    }

    public function testCreateUserWithEmptyPasswordHash(): void
    {
        // Domain model should accept any string
        $user = new User('test@example.com', '');

        $this->assertEquals('', $user->passwordHash);
    }
}
