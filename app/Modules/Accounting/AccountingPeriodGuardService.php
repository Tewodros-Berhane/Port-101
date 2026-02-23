<?php

namespace App\Modules\Accounting;

use App\Core\Settings\SettingsService;
use Carbon\CarbonImmutable;

class AccountingPeriodGuardService
{
    public function __construct(
        private readonly SettingsService $settingsService,
    ) {}

    public function assertPostingAllowed(string $companyId, string $postingDate): void
    {
        $date = CarbonImmutable::parse($postingDate)->startOfDay();

        $closedUntil = $this->settingsService->get(
            key: 'company.accounting.closed_until',
            default: null,
            companyId: $companyId,
        );

        if ($closedUntil) {
            $lockedUntil = CarbonImmutable::parse((string) $closedUntil)->endOfDay();

            if ($date->lessThanOrEqualTo($lockedUntil)) {
                abort(422, sprintf(
                    'Posting date is in a closed period (locked through %s).',
                    $lockedUntil->toDateString(),
                ));
            }
        }
    }
}
