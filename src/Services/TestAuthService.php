<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Simple file-based authentication service for testing without database
 */
class TestAuthService implements AuthServiceInterface
{
    private array $users;
    
    public function __construct()
    {
        // Load test users from JSON file
        $testDataPath = __DIR__ . '/../../test_data.json';
        if (file_exists($testDataPath)) {
            $data = json_decode(file_get_contents($testDataPath), true);
            $this->users = $data['users'] ?? [];
        } else {
            // Fallback hardcoded users
            $this->users = [
                [
                    'id' => 1,
                    'username' => 'admin',
                    'email' => 'admin@assurify.local',
                    'password_hash' => '$argon2id$v=19$m=65536,t=4,p=1$TWtqWmVveVpVTjVoZ3kuYg$v7Q3lm/NIvHmZZsHecj9M90Nn0sM5bdtumJwNVvAAbk',
                    'karma' => 1000,
                    'is_admin' => true,
                    'is_moderator' => true,
                    'is_banned' => false,
                    'session_token' => null
                ],
                [
                    'id' => 2,
                    'username' => 'testuser',
                    'email' => 'test@example.com',
                    'password_hash' => '$argon2id$v=19$m=65536,t=4,p=1$ekwyNW1ocTdkajNZLkpWWg$p3ZSOSz6Rn7wtnvzyTZMcfHGblEynoguoFGf8eLBwmY',
                    'karma' => 100,
                    'is_admin' => false,
                    'is_moderator' => false,
                    'is_banned' => false,
                    'session_token' => null
                ]
            ];
        }
    }
    
    public function authenticateUser(string $username, string $password)
    {
        error_log("TestAuthService: Attempting authentication for username: $username");
        
        foreach ($this->users as $user) {
            error_log("TestAuthService: Checking user: " . $user['username']);
            if ($user['username'] === $username || $user['email'] === $username) {
                error_log("TestAuthService: Found matching user: " . $user['username']);
                error_log("TestAuthService: Verifying password...");
                
                if (password_verify($password, $user['password_hash'])) {
                    error_log("TestAuthService: Password verified successfully");
                    
                    // Generate session token
                    $sessionToken = bin2hex(random_bytes(32));
                    $user['session_token'] = $sessionToken;
                    
                    // Update session in memory (for this request)
                    $this->updateUserSession($user['id'], $sessionToken);
                    
                    error_log("TestAuthService: Returning authenticated user object");
                    // Return a simple object with the same interface as the model
                    return (object) $user;
                } else {
                    error_log("TestAuthService: Password verification failed");
                }
            }
        }
        
        error_log("TestAuthService: Authentication failed - no matching user found");
        return null;
    }
    
    public function getUserBySessionToken(string $sessionToken)
    {
        foreach ($this->users as $user) {
            if ($user['session_token'] === $sessionToken) {
                return $user;
            }
        }
        
        return null;
    }
    
    public function getUserById(int $id)
    {
        foreach ($this->users as $user) {
            if ($user['id'] === $id) {
                return $user;
            }
        }
        
        return null;
    }
    
    public function getUserByUsername(string $username): ?array
    {
        foreach ($this->users as $user) {
            if ($user['username'] === $username) {
                return $user;
            }
        }
        
        return null;
    }
    
    private function updateUserSession(int $userId, string $sessionToken): void
    {
        for ($i = 0; $i < count($this->users); $i++) {
            if ($this->users[$i]['id'] === $userId) {
                $this->users[$i]['session_token'] = $sessionToken;
                break;
            }
        }
    }
    
    public function logout(string $sessionToken): bool
    {
        for ($i = 0; $i < count($this->users); $i++) {
            if ($this->users[$i]['session_token'] === $sessionToken) {
                $this->users[$i]['session_token'] = null;
                return true;
            }
        }
        
        return false;
    }
    
    public function isAdmin(array $user): bool
    {
        return $user['is_admin'] ?? false;
    }
    
    public function isModerator(array $user): bool
    {
        return $user['is_moderator'] ?? false;
    }
    
    public function getAllUsers(): array
    {
        return $this->users;
    }
}