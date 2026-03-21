<?php

namespace App\Http\Requests\Platform;

use App\Core\Access\Models\Invite;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AdminUserStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $email = strtolower(trim((string) $value));

                    $existingSuperAdmin = User::query()
                        ->whereRaw('LOWER(email) = ?', [$email])
                        ->where('is_super_admin', true)
                        ->exists();

                    if ($existingSuperAdmin) {
                        $fail('This user is already a platform admin.');

                        return;
                    }

                    $pendingInviteExists = Invite::query()
                        ->whereRaw('LOWER(email) = ?', [$email])
                        ->where('role', 'platform_admin')
                        ->whereNull('accepted_at')
                        ->exists();

                    if ($pendingInviteExists) {
                        $fail('A pending platform admin invite already exists for this email.');
                    }
                },
            ],
        ];
    }
}
