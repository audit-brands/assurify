<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Common interface for authentication services
 */
interface AuthServiceInterface
{
    public function authenticateUser(string $username, string $password);
    public function getUserBySessionToken(string $sessionToken);
    public function getUserById(int $id);
    public function logout(string $sessionToken): bool;
}