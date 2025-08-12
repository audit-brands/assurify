<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invitation;
use App\Models\User;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

class EmailService
{
    private Mailer $mailer;

    public function __construct()
    {
        // Create mailer with SMTP transport
        $dsn = sprintf(
            'smtp://%s:%s@%s:%s',
            $_ENV['MAIL_USERNAME'] ?? '',
            $_ENV['MAIL_PASSWORD'] ?? '',
            $_ENV['MAIL_HOST'] ?? 'localhost',
            $_ENV['MAIL_PORT'] ?? '587'
        );

        $transport = Transport::fromDsn($dsn);
        $this->mailer = new Mailer($transport);
    }

    public function sendInvitation(Invitation $invitation): void
    {
        $inviter = $invitation->user;
        $invitationUrl = $_ENV['APP_URL'] . '/signup/invited?code=' . $invitation->code;

        $email = (new Email())
            ->from($_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@example.com')
            ->to($invitation->email)
            ->subject('You\'ve been invited to join Lobsters')
            ->html($this->renderInvitationEmail($inviter, $invitation, $invitationUrl));

        $this->mailer->send($email);
    }

    public function sendPasswordReset(User $user, string $resetToken): void
    {
        $resetUrl = $_ENV['APP_URL'] . '/auth/reset-password?token=' . $resetToken;

        $email = (new Email())
            ->from($_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@example.com')
            ->to($user->email)
            ->subject('Password Reset Request')
            ->html($this->renderPasswordResetEmail($user, $resetUrl));

        $this->mailer->send($email);
    }

    public function sendWelcomeEmail(User $user): void
    {
        $email = (new Email())
            ->from($_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@example.com')
            ->to($user->email)
            ->subject('Welcome to Lobsters!')
            ->html($this->renderWelcomeEmail($user));

        $this->mailer->send($email);
    }

    private function renderInvitationEmail(User $inviter, Invitation $invitation, string $invitationUrl): string
    {
        return "
        <html>
        <body>
            <h2>You've been invited to join Lobsters</h2>
            <p>Hello!</p>
            <p><strong>{$inviter->username}</strong> has invited you to join the Lobsters community.</p>
            
            " . (!empty($invitation->memo) ? "<p><em>Personal message:</em> {$invitation->memo}</p>" : "") . "
            
            <p>Lobsters is a computing-focused community centered around link aggregation and discussion.</p>
            
            <p><a href=\"{$invitationUrl}\" style=\"background: #ff6600; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;\">Accept Invitation</a></p>
            
            <p>This invitation link will expire in 30 days.</p>
            
            <p>If you have any questions, feel free to reply to this email.</p>
            
            <p>Best regards,<br>The Lobsters Team</p>
        </body>
        </html>
        ";
    }

    private function renderPasswordResetEmail(User $user, string $resetUrl): string
    {
        return "
        <html>
        <body>
            <h2>Password Reset Request</h2>
            <p>Hello {$user->username},</p>
            <p>We received a request to reset your password for your Lobsters account.</p>
            
            <p><a href=\"{$resetUrl}\" style=\"background: #ff6600; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;\">Reset Password</a></p>
            
            <p>This link will expire in 1 hour for security reasons.</p>
            
            <p>If you didn't request this reset, you can safely ignore this email.</p>
            
            <p>Best regards,<br>The Lobsters Team</p>
        </body>
        </html>
        ";
    }

    private function renderWelcomeEmail(User $user): string
    {
        return "
        <html>
        <body>
            <h2>Welcome to Lobsters!</h2>
            <p>Hello {$user->username},</p>
            <p>Welcome to the Lobsters community! We're excited to have you join us.</p>
            
            <p>Here are a few things to get you started:</p>
            <ul>
                <li><strong>Read the guidelines:</strong> Check out our community standards</li>
                <li><strong>Complete your profile:</strong> Add a bio and interests</li>
                <li><strong>Start engaging:</strong> Comment on stories and submit interesting links</li>
            </ul>
            
            <p>If you have any questions, don't hesitate to reach out to the moderators.</p>
            
            <p>Happy browsing!<br>The Lobsters Team</p>
        </body>
        </html>
        ";
    }
}
