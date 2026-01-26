<?php

namespace App\Core\Support;

use App\Core\Company\Models\Company;
use RuntimeException;

class CompanyContext
{
    private ?Company $company = null;

    public function set(?Company $company): void
    {
        $this->company = $company;
    }

    public function get(): ?Company
    {
        return $this->company;
    }

    public function require(): Company
    {
        if (! $this->company) {
            throw new RuntimeException('Company context is not set.');
        }

        return $this->company;
    }
}
