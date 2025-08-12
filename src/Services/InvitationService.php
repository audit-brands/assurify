<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Invitation;
use Illuminate\Support\Carbon;

class InvitationService
{
    public function __construct(private EmailService $emailService)
    {
    }

    public function generateInvitationCode(): string
    {
        // Generate a unique 32-character invitation code
        do {
            $code = bin2hex(random_bytes(16));
        } while (Invitation::where('code', $code)->exists());

        return $code;
    }

    public function createInvitation(User $inviter, string $email, string $memo = ''): Invitation
    {
        // Check if user can send invitations
        if (!$this->canUserInvite($inviter)) {
            throw new \Exception('User cannot send invitations');
        }

        // Check if email is already invited or registered
        if (User::where('email', $email)->exists()) {
            throw new \Exception('Email is already registered');
        }

        if (Invitation::where('email', $email)->whereNull('used_at')->exists()) {
            throw new \Exception('Email already has a pending invitation');
        }

        $invitation = new Invitation();
        $invitation->user_id = $inviter->id;
        $invitation->email = $email;
        $invitation->code = $this->generateInvitationCode();
        $invitation->memo = $memo;
        $invitation->save();

        // Send invitation email
        $this->emailService->sendInvitation($invitation);

        return $invitation;
    }

    public function canUserInvite(User $user): bool
    {
        // Check if user is banned or has disabled invites
        if ($user->banned_at !== null || ($user->disabled_invites ?? false)) {
            return false;
        }

        // Check karma requirements (minimum 5 karma to invite)
        if ($user->karma < 5) {
            return false;
        }

        // Check recent invitation limits (max 5 per week)
        $recentInvitations = Invitation::where('user_id', $user->id)
            ->where('created_at', '>=', Carbon::now()->subWeek())
            ->count();

        return $recentInvitations < 5;
    }

    public function getUserInvitations(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return Invitation::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getInvitationByCode(string $code): ?Invitation
    {
        return Invitation::where('code', $code)->first();
    }

    public function getInvitationStats(User $user): array
    {
        $total = Invitation::where('user_id', $user->id)->count();
        $used = Invitation::where('user_id', $user->id)->whereNotNull('used_at')->count();
        $pending = $total - $used;

        return [
            'total' => $total,
            'used' => $used,
            'pending' => $pending,
            'can_invite' => $this->canUserInvite($user),
            'remaining_this_week' => max(0, 5 - Invitation::where('user_id', $user->id)
                ->where('created_at', '>=', Carbon::now()->subWeek())
                ->count())
        ];
    }
}
