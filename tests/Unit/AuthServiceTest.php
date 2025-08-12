<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\AuthService;

class AuthServiceTest extends TestCase
{
    private AuthService $authService;

    protected function setUp(): void
    {
        $this->authService = new AuthService();
    }

    public function testHashPassword(): void
    {
        $password = 'testpassword123';
        $hash = $this->authService->hashPassword($password);

        $this->assertNotEmpty($hash);
        $this->assertNotEquals($password, $hash);
        $this->assertTrue(password_verify($password, $hash));
    }

    public function testVerifyPassword(): void
    {
        $password = 'testpassword123';
        $hash = $this->authService->hashPassword($password);

        $this->assertTrue($this->authService->verifyPassword($password, $hash));
        $this->assertFalse($this->authService->verifyPassword('wrongpassword', $hash));
    }

    public function testGenerateSessionToken(): void
    {
        $token1 = $this->authService->generateSessionToken();
        $token2 = $this->authService->generateSessionToken();

        $this->assertNotEmpty($token1);
        $this->assertNotEmpty($token2);
        $this->assertNotEquals($token1, $token2);
        $this->assertIsString($token1);
        $this->assertIsString($token2);
    }

    public function testIsValidUsername(): void
    {
        // Valid usernames
        $this->assertTrue($this->authService->isValidUsername('testuser'));
        $this->assertTrue($this->authService->isValidUsername('test_user'));
        $this->assertTrue($this->authService->isValidUsername('test-user'));
        $this->assertTrue($this->authService->isValidUsername('user123'));
        $this->assertTrue($this->authService->isValidUsername('abc'));

        // Invalid usernames
        $this->assertFalse($this->authService->isValidUsername('ab')); // too short
        $this->assertFalse($this->authService->isValidUsername(str_repeat('a', 51))); // too long
        $this->assertFalse($this->authService->isValidUsername('test user')); // space
        $this->assertFalse($this->authService->isValidUsername('test.user')); // dot
        $this->assertFalse($this->authService->isValidUsername('test@user')); // @
        $this->assertFalse($this->authService->isValidUsername('')); // empty
    }

    public function testIsValidEmail(): void
    {
        // Valid emails
        $this->assertTrue($this->authService->isValidEmail('test@example.com'));
        $this->assertTrue($this->authService->isValidEmail('user.name@domain.co.uk'));
        $this->assertTrue($this->authService->isValidEmail('test+tag@example.org'));

        // Invalid emails
        $this->assertFalse($this->authService->isValidEmail('invalid'));
        $this->assertFalse($this->authService->isValidEmail('test@'));
        $this->assertFalse($this->authService->isValidEmail('@example.com'));
        $this->assertFalse($this->authService->isValidEmail('test space@example.com'));
        $this->assertFalse($this->authService->isValidEmail(''));
    }

    public function testGeneratePasswordResetToken(): void
    {
        $token1 = $this->authService->generatePasswordResetToken();
        $token2 = $this->authService->generatePasswordResetToken();

        $this->assertNotEmpty($token1);
        $this->assertNotEmpty($token2);
        $this->assertNotEquals($token1, $token2);
        $this->assertEquals(64, strlen($token1)); // bin2hex(random_bytes(32)) = 64 chars
        $this->assertEquals(64, strlen($token2));
        $this->assertTrue(ctype_xdigit($token1)); // only hex characters
        $this->assertTrue(ctype_xdigit($token2));
    }
}
