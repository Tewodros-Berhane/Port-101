<?php

namespace App\Providers;

use App\Core\Company\Models\Company;
use App\Core\MasterData\Models\Currency;
use App\Core\MasterData\Models\Partner;
use App\Core\MasterData\Models\PriceList;
use App\Core\MasterData\Models\Product;
use App\Core\MasterData\Models\Tax;
use App\Core\MasterData\Models\Uom;
use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use App\Policies\CompanyPolicy;
use App\Policies\CurrencyPolicy;
use App\Policies\PartnerPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\PriceListPolicy;
use App\Policies\ProductPolicy;
use App\Policies\RolePolicy;
use App\Policies\TaxPolicy;
use App\Policies\UomPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Company::class => CompanyPolicy::class,
        Role::class => RolePolicy::class,
        Permission::class => PermissionPolicy::class,
        Partner::class => PartnerPolicy::class,
        Product::class => ProductPolicy::class,
        Tax::class => TaxPolicy::class,
        Currency::class => CurrencyPolicy::class,
        Uom::class => UomPolicy::class,
        PriceList::class => PriceListPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
