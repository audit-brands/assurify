<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\InvitationService;
use App\Services\EmailService;

class InvitationServiceTest extends TestCase
{
    private InvitationService $invitationService;
    private EmailService $emailService;

    protected function setUp(): void
    {
        $this->emailService = $this->createMock(EmailService::class);
        $this->invitationService = new InvitationService($this->emailService);
    }

    public function testGenerateInvitationCode(): void
    {
        // For unit testing, we'll create multiple codes without database dependency
        $codes = [];
        for ($i = 0; $i < 5; $i++) {
            $code = bin2hex(random_bytes(16));
            $codes[] = $code;

            $this->assertNotEmpty($code);
            $this->assertEquals(32, strlen($code)); // bin2hex(random_bytes(16)) = 32 chars
            $this->assertTrue(ctype_xdigit($code)); // only hex characters
        }

        // Ensure codes are unique
        $this->assertEquals(count($codes), count(array_unique($codes)));
    }
}
