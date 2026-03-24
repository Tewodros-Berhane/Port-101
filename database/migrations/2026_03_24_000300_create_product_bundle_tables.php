<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_bundles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('mode', 32)->default('sales_only');
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'mode']);
            $table->index(['company_id', 'is_active']);
        });

        DB::statement(
            'create unique index product_bundles_product_unique on product_bundles (company_id, product_id) where deleted_at is null'
        );

        Schema::create('product_bundle_components', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('bundle_id')->constrained('product_bundles')->cascadeOnDelete();
            $table->foreignUuid('component_product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('sequence')->default(1);
            $table->decimal('quantity', 18, 4);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'bundle_id']);
            $table->index(['company_id', 'component_product_id']);
        });

        DB::statement(
            'create unique index product_bundle_components_scope_unique on product_bundle_components (bundle_id, component_product_id) where deleted_at is null'
        );
    }

    public function down(): void
    {
        DB::statement('drop index if exists product_bundle_components_scope_unique');
        Schema::dropIfExists('product_bundle_components');

        DB::statement('drop index if exists product_bundles_product_unique');
        Schema::dropIfExists('product_bundles');
    }
};
