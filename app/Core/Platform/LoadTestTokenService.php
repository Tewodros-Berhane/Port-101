<?php

namespace App\Core\Platform;

use App\Core\Company\Models\Company;
use App\Core\Company\Models\CompanyUser;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoadTestTokenService
{
    /**
     * @return array{user: User, company: Company, token_name: string, token: string, abilities: array<int, string>}
     */
    public function issue(
        ?string $email = null,
        ?string $companyReference = null,
        string $tokenName = 'ops-load-test',
        array $abilities = ['*'],
        bool $revokeExisting = true,
    ): array {
        $company = $this->resolveCompany($companyReference);
        $user = $this->resolveUser($email, $company);

        if (! $user->is_super_admin && ! $this->userBelongsToCompany($user, $company)) {
            throw ValidationException::withMessages([
                'user' => ["User [{$user->email}] does not belong to company [{$company->slug}]."],
            ]);
        }

        if ((string) $user->current_company_id !== (string) $company->id) {
            $user->forceFill([
                'current_company_id' => $company->id,
            ])->save();
        }

        if ($revokeExisting) {
            $user->tokens()->where('name', $tokenName)->delete();
        }

        $abilities = $this->normalizeAbilities($abilities);
        $token = $user->createToken($tokenName, $abilities)->plainTextToken;

        return [
            'user' => $user->fresh() ?? $user,
            'company' => $company,
            'token_name' => $tokenName,
            'token' => $token,
            'abilities' => $abilities,
        ];
    }

    private function resolveCompany(?string $reference = null): Company
    {
        $query = Company::query()->where('is_active', true);

        if (is_string($reference) && trim($reference) !== '') {
            $normalizedReference = trim($reference);
            $company = Str::isUuid($normalizedReference)
                ? (clone $query)->where('id', $normalizedReference)->first()
                : (clone $query)->where('slug', $normalizedReference)->first();

            if ($company) {
                return $company;
            }

            throw ValidationException::withMessages([
                'company' => ["Active company [{$normalizedReference}] was not found."],
            ]);
        }

        $preferredSlug = (string) config('core.integration.smoke_check_company_slug', 'demo-company-workflow');
        $preferred = (clone $query)->where('slug', $preferredSlug)->first();

        if ($preferred) {
            return $preferred;
        }

        $company = (clone $query)->orderBy('name')->first();

        if ($company) {
            return $company;
        }

        throw ValidationException::withMessages([
            'company' => ['No active company is available for load testing.'],
        ]);
    }

    private function resolveUser(?string $email, Company $company): User
    {
        if (is_string($email) && trim($email) !== '') {
            $user = User::query()->where('email', trim($email))->first();

            if ($user) {
                return $user;
            }

            throw ValidationException::withMessages([
                'user' => ["User [{$email}] was not found."],
            ]);
        }

        $membership = CompanyUser::query()
            ->where('company_id', $company->id)
            ->orderByDesc('is_owner')
            ->orderBy('created_at')
            ->with('user')
            ->first();

        if ($membership?->user) {
            return $membership->user;
        }

        throw ValidationException::withMessages([
            'user' => ["Company [{$company->slug}] has no users available for load testing."],
        ]);
    }

    private function userBelongsToCompany(User $user, Company $company): bool
    {
        return CompanyUser::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * @param  array<int, string>  $abilities
     * @return array<int, string>
     */
    private function normalizeAbilities(array $abilities): array
    {
        $normalized = collect($abilities)
            ->filter(fn ($ability) => is_string($ability) && trim($ability) !== '')
            ->map(fn ($ability) => trim((string) $ability))
            ->unique()
            ->values()
            ->all();

        return $normalized === [] ? ['*'] : $normalized;
    }
}
