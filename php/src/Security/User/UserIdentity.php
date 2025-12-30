<?php

namespace App\Security\User;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class UserIdentity implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct(
        private string $email,
        private string $passwordHash,
        private array $roles = ['ROLE_USER']
    ) {}

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        // guarantee every user at least has ROLE_USER
        return array_values(array_unique(array_merge($this->roles, ['ROLE_USER'])));
    }

    public function eraseCredentials(): void
    {
        // no-op; we don't store plain credentials here
    }

    public function getPassword(): ?string
    {
        return $this->passwordHash;
    }
}
