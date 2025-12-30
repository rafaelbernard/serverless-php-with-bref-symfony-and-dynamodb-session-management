<?php

namespace App\Tests\Unit\Security\User;

use App\Security\User\UserIdentity;
use PHPUnit\Framework\TestCase;

class UserIdentityTest extends TestCase
{
    public function testGetUserIdentifierReturnsEmail(): void
    {
        $email = 'test@example.com';
        $passwordHash = 'hashed_password';

        $identity = new UserIdentity($email, $passwordHash);

        $this->assertEquals($email, $identity->getUserIdentifier());
    }

    public function testGetPasswordReturnsPasswordHash(): void
    {
        $email = 'test@example.com';
        $passwordHash = 'hashed_password';

        $identity = new UserIdentity($email, $passwordHash);

        $this->assertEquals($passwordHash, $identity->getPassword());
    }

    public function testGetRolesReturnsDefaultRoleUser(): void
    {
        $identity = new UserIdentity('test@example.com', 'hash');

        $roles = $identity->getRoles();

        $this->assertContains('ROLE_USER', $roles);
    }

    public function testGetRolesReturnsCustomRoles(): void
    {
        $customRoles = ['ROLE_ADMIN', 'ROLE_EDITOR'];
        $identity = new UserIdentity('test@example.com', 'hash', $customRoles);

        $roles = $identity->getRoles();

        $this->assertContains('ROLE_USER', $roles);
        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertContains('ROLE_EDITOR', $roles);
    }

    public function testGetRolesAlwaysIncludesRoleUser(): void
    {
        $customRoles = ['ROLE_ADMIN'];
        $identity = new UserIdentity('test@example.com', 'hash', $customRoles);

        $roles = $identity->getRoles();

        // ROLE_USER should be included even if not explicitly passed
        $this->assertContains('ROLE_USER', $roles);
        $this->assertContains('ROLE_ADMIN', $roles);
    }

    public function testGetRolesRemovesDuplicates(): void
    {
        $customRoles = ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_USER'];
        $identity = new UserIdentity('test@example.com', 'hash', $customRoles);

        $roles = $identity->getRoles();

        // Should have unique values only
        $this->assertEquals(array_values(array_unique($roles)), $roles);
        $this->assertCount(2, $roles); // ROLE_USER and ROLE_ADMIN
    }

    public function testEraseCredentialsDoesNothing(): void
    {
        $identity = new UserIdentity('test@example.com', 'hash');

        // Should not throw exception
        $identity->eraseCredentials();

        // Password should still be accessible
        $this->assertEquals('hash', $identity->getPassword());
    }

    public function testConstructorWithMinimalParameters(): void
    {
        $identity = new UserIdentity('test@example.com', 'hash');

        $this->assertInstanceOf(UserIdentity::class, $identity);
        $this->assertEquals('test@example.com', $identity->getUserIdentifier());
        $this->assertEquals('hash', $identity->getPassword());
        $this->assertEquals(['ROLE_USER'], $identity->getRoles());
    }

    public function testImplementsUserInterface(): void
    {
        $identity = new UserIdentity('test@example.com', 'hash');

        $this->assertInstanceOf(\Symfony\Component\Security\Core\User\UserInterface::class, $identity);
    }

    public function testImplementsPasswordAuthenticatedUserInterface(): void
    {
        $identity = new UserIdentity('test@example.com', 'hash');

        $this->assertInstanceOf(
            \Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface::class,
            $identity
        );
    }
}
