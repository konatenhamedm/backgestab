<?php

namespace App\Security\Application\DTO;

final class LoginDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly ?string $ip = null
    ) {}
}
