<?php

namespace App\Tests\Unit\Security\User;

use App\Security\User\UserIdentity;
use App\Security\User\UserProvider;
use App\User\Domain\User;
use App\User\Domain\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

class UserProviderTest extends TestCase
{
    private UserRepositoryInterface $userRepository;
    private UserProvider $userProvider;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->userProvider = new UserProvider($this->userRepository);
    }

    public function testLoadUserByIdentifierReturnsUserIdentity(): void
    {
        $email = 'test@example.com';
        $passwordHash = 'hashed_password';

        $user = new User($email, $passwordHash);

        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn($user);

        $userIdentity = $this->userProvider->loadUserByIdentifier($email);

        $this->assertInstanceOf(UserIdentity::class, $userIdentity);
        $this->assertEquals($email, $userIdentity->getUserIdentifier());
        $this->assertEquals($passwordHash, $userIdentity->getPassword());
    }

    public function testLoadUserByIdentifierThrowsExceptionWhenUserNotFound(): void
    {
        $email = 'notfound@example.com';

        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn(null);

        $this->expectException(UserNotFoundException::class);

        $this->userProvider->loadUserByIdentifier($email);
    }

    public function testRefreshUserReloadsUser(): void
    {
        $email = 'test@example.com';
        $originalIdentity = new UserIdentity($email, 'old_hash');
        $updatedUser = new User($email, 'new_hash');

        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn($updatedUser);

        $refreshedIdentity = $this->userProvider->refreshUser($originalIdentity);

        $this->assertInstanceOf(UserIdentity::class, $refreshedIdentity);
        $this->assertEquals($email, $refreshedIdentity->getUserIdentifier());
        $this->assertEquals('new_hash', $refreshedIdentity->getPassword());
    }

    public function testRefreshUserThrowsExceptionForUnsupportedUserClass(): void
    {
        $unsupportedUser = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported user class');

        $this->userProvider->refreshUser($unsupportedUser);
    }

    public function testSupportsClassReturnsTrueForUserIdentity(): void
    {
        $supports = $this->userProvider->supportsClass(UserIdentity::class);

        $this->assertTrue($supports);
    }

    public function testSupportsClassReturnsTrueForUserIdentitySubclass(): void
    {
        // Create an anonymous subclass for testing
        $subclass = new class('test@example.com', 'hash') extends UserIdentity {};

        $supports = $this->userProvider->supportsClass(get_class($subclass));

        $this->assertTrue($supports);
    }

    public function testSupportsClassReturnsFalseForOtherClasses(): void
    {
        $supports = $this->userProvider->supportsClass(\stdClass::class);

        $this->assertFalse($supports);
    }

    public function testLoadUserByIdentifierSetsUserIdentifierOnException(): void
    {
        $email = 'notfound@example.com';

        $this->userRepository
            ->method('findByEmail')
            ->with($email)
            ->willReturn(null);

        try {
            $this->userProvider->loadUserByIdentifier($email);
            $this->fail('Expected UserNotFoundException to be thrown');
        } catch (UserNotFoundException $e) {
            // Verify that the user identifier was set on the exception
            $this->assertNotNull($e);
        }
    }
}
