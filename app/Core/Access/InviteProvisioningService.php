<?php

namespace App\Core\Access;

use App\Core\Access\Models\Invite;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InviteProvisioningService
{
    public function createOrRefreshPendingInvite(
        string $email,
        ?string $name,
        string $role,
        ?string $companyId,
        CarbonInterface $expiresAt,
        ?string $actorId = null,
    ): Invite {
        $normalizedEmail = Invite::normalizeEmail($email);

        return DB::transaction(function () use (
            $normalizedEmail,
            $name,
            $role,
            $companyId,
            $expiresAt,
            $actorId,
        ): Invite {
            $invite = Invite::query()
                ->pending()
                ->forTarget($normalizedEmail, $role, $companyId)
                ->lockForUpdate()
                ->first();

            $attributes = [
                'email' => $normalizedEmail,
                'name' => $name,
                'role' => $role,
                'company_id' => $companyId,
                'token' => Str::random(40),
                'expires_at' => $expiresAt,
                'delivery_status' => Invite::DELIVERY_PENDING,
                'delivery_attempts' => 0,
                'last_delivery_at' => null,
                'last_delivery_error' => null,
                'created_by' => $actorId,
            ];

            if ($invite) {
                $invite->forceFill($attributes)->save();

                return $invite->fresh() ?? $invite;
            }

            return Invite::create($attributes);
        });
    }
}
