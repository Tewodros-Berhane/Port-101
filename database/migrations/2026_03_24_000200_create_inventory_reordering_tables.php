<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_reorder_rules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->foreignUuid('preferred_vendor_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->decimal('min_quantity', 18, 4)->default(0);
            $table->decimal('max_quantity', 18, 4);
            $table->decimal('reorder_quantity', 18, 4)->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_evaluated_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'product_id']);
            $table->index(['company_id', 'location_id']);
        });

        DB::statement(
            'create unique index inventory_reorder_rules_scope_unique on inventory_reorder_rules (company_id, product_id, location_id) where deleted_at is null'
        );

        Schema::create('inventory_replenishment_suggestions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('reorder_rule_id')->constrained('inventory_reorder_rules')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->foreignUuid('preferred_vendor_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->foreignUuid('rfq_id')->nullable()->constrained('purchase_rfqs')->nullOnDelete();
            $table->string('status')->default('open');
            $table->decimal('on_hand_quantity', 18, 4)->default(0);
            $table->decimal('reserved_quantity', 18, 4)->default(0);
            $table->decimal('available_quantity', 18, 4)->default(0);
            $table->decimal('inbound_quantity', 18, 4)->default(0);
            $table->decimal('projected_quantity', 18, 4)->default(0);
            $table->decimal('min_quantity', 18, 4)->default(0);
            $table->decimal('max_quantity', 18, 4)->default(0);
            $table->decimal('suggested_quantity', 18, 4)->default(0);
            $table->timestamp('triggered_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'product_id']);
            $table->index(['company_id', 'location_id']);
            $table->index(['company_id', 'reorder_rule_id', 'status'], 'inventory_replenishment_rule_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_replenishment_suggestions');
        DB::statement('drop index if exists inventory_reorder_rules_scope_unique');
        Schema::dropIfExists('inventory_reorder_rules');
    }
};
