<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Core\Company\Models\Company;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'company_name' => ['required', 'string', 'max:255'],
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $companyName = trim($input['company_name']);
            $baseSlug = Str::slug($companyName);
            $baseSlug = $baseSlug !== '' ? $baseSlug : Str::lower(Str::random(8));
            $slug = $baseSlug;
            $counter = 1;

            while (Company::where('slug', $slug)->exists()) {
                $counter += 1;
                $slug = $baseSlug.'-'.$counter;
            }

            $company = Company::create([
                'name' => $companyName,
                'slug' => $slug,
                'timezone' => config('app.timezone', 'UTC'),
                'owner_id' => $user->id,
            ]);

            $ownerRole = Role::query()
                ->whereNull('company_id')
                ->where('slug', 'owner')
                ->first();

            $company->users()->attach($user->id, [
                'role_id' => $ownerRole?->id,
                'is_owner' => true,
            ]);

            $user->forceFill([
                'current_company_id' => $company->id,
            ])->save();

            return $user;
        });
    }
}
