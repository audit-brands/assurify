<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Invitation;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Carbon;

class AuthService
{
    public function __construct()
    {
    }

    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3,         // 3 threads
        ]);
    }

    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function generateSessionToken(): string
    {
        return Uuid::uuid4()->toString();
    }

    public function authenticateUser(string $username, string $password): ?User
    {
        // Find user by username or email
        $user = User::where('username', $username)
                   ->orWhere('email', $username)
                   ->first();

        if (!$user || !$this->verifyPassword($password, $user->password_hash)) {
            return null;
        }

        // Check if user is banned
        if ($user->banned_at !== null) {
            return null;
        }

        // Generate new session token
        $user->session_token = $this->generateSessionToken();
        $user->save();

        return $user;
    }

    public function registerUser(array $userData, string $invitationCode): ?User
    {
        // Validate invitation code
        $invitation = $this->validateInvitationCode($invitationCode);
        if (!$invitation) {
            throw new \Exception('Invalid invitation code');
        }

        // Check if username or email already exists
        if (User::where('username', $userData['username'])->exists()) {
            throw new \Exception('Username already taken');
        }

        if (User::where('email', $userData['email'])->exists()) {
            throw new \Exception('Email already registered');
        }

        // Create user
        $user = new User();
        $user->username = $userData['username'];
        $user->email = $userData['email'];
        $user->password_hash = $this->hashPassword($userData['password']);
        $user->about = $userData['about'] ?? '';
        $user->session_token = $this->generateSessionToken();
        $user->save();

        // Mark invitation as used
        $invitation->used_at = Carbon::now();
        $invitation->save();

        return $user;
    }

    public function validateInvitationCode(string $code): ?Invitation
    {
        return Invitation::where('code', $code)
                        ->whereNull('used_at')
                        ->first();
    }

    public function logout(User $user): void
    {
        $user->session_token = null;
        $user->save();
    }

    public function getUserBySessionToken(string $token): ?User
    {
        return User::where('session_token', $token)->first();
    }

    public function generatePasswordResetToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function isValidUsername(string $username): bool
    {
        // Username should be 3-50 chars, alphanumeric + underscore/dash
        return preg_match('/^[a-zA-Z0-9_-]{3,50}$/', $username) === 1;
    }

    public function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
