<?php

namespace App\User\Domain;

interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    /**
     * @throws \RuntimeException if user with email already exists
     */
    public function create(string $email, string $passwordHash): User;
}
