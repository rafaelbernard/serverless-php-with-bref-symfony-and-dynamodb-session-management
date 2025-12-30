<?php

namespace App\Security\User;

use App\User\Domain\UserRepositoryInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final readonly class UserProvider implements UserProviderInterface
{
    public function __construct(private UserRepositoryInterface $users) {}

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $start = microtime(true);
        error_log("USER LOAD START: $identifier");
        
        $user = $this->users->findByEmail($identifier);
        
        $elapsed = round((microtime(true) - $start) * 1000, 2);
        
        if (!$user) {
            error_log("USER LOAD NOT FOUND: {$elapsed}ms");
            $ex = new UserNotFoundException();
            $ex->setUserIdentifier($identifier);
            throw $ex;
        }
        
        error_log("USER LOAD SUCCESS: {$elapsed}ms");
        return new UserIdentity($user->email, $user->passwordHash);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof UserIdentity) {
            throw new \InvalidArgumentException('Unsupported user class: '.get_class($user));
        }
        // reload to fetch latest credentials/roles if changed
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === UserIdentity::class || is_subclass_of($class, UserIdentity::class);
    }
}
