<?php

namespace App\User\Domain;

final class User
{
    public function __construct(
        public readonly string $email,
        public readonly string $passwordHash,
    ) {}
}
