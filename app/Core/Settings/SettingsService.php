<?php

namespace App\Core\Settings;

use App\Core\Settings\Models\Setting;

class SettingsService
{
    public function get(
        string $key,
        mixed $default = null,
        ?string $companyId = null,
        ?string $userId = null
    ): mixed {
        $setting = $this->baseQuery($companyId, $userId)
            ->where('key', $key)
            ->first();

        return $setting?->value ?? $default;
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public function getMany(
        array $keys,
        ?string $companyId = null,
        ?string $userId = null
    ): array {
        if ($keys === []) {
            return [];
        }

        $settings = $this->baseQuery($companyId, $userId)
            ->whereIn('key', $keys)
            ->get(['key', 'value'])
            ->pluck('value', 'key')
            ->all();

        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $settings[$key] ?? null;
        }

        return $result;
    }

    public function set(
        string $key,
        mixed $value,
        ?string $companyId = null,
        ?string $userId = null,
        ?string $actorId = null
    ): Setting {
        $setting = Setting::query()->firstOrNew([
            'company_id' => $companyId,
            'user_id' => $userId,
            'key' => $key,
        ]);

        $setting->fill([
            'value' => $value,
            'updated_by' => $actorId,
        ]);

        if (! $setting->exists) {
            $setting->created_by = $actorId;
        }

        $setting->save();

        return $setting;
    }

    public function forget(
        string $key,
        ?string $companyId = null,
        ?string $userId = null
    ): int {
        return $this->baseQuery($companyId, $userId)
            ->where('key', $key)
            ->delete();
    }

    private function baseQuery(?string $companyId, ?string $userId)
    {
        $query = Setting::query();

        if ($companyId) {
            $query->where('company_id', $companyId);
        } else {
            $query->whereNull('company_id');
        }

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->whereNull('user_id');
        }

        return $query;
    }
}

