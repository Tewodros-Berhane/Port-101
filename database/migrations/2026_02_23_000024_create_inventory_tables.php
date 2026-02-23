<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_warehouses', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'name']);
            $table->index('created_by');
            $table->index('updated_by');
        });

        Schema::create('inventory_locations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('warehouse_id')->nullable()->constrained('inventory_warehouses')->nullOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('type', 24)->default('internal');
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'type']);
            $table->index('warehouse_id');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Schema::create('inventory_stock_levels', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('on_hand_quantity', 15, 4)->default(0);
            $table->decimal('reserved_quantity', 15, 4)->default(0);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'location_id', 'product_id'], 'inventory_levels_company_location_product_unique');
            $table->index(['company_id', 'product_id']);
            $table->index(['company_id', 'location_id']);
            $table->index('created_by');
            $table->index('updated_by');
        });

        Schema::create('inventory_stock_moves', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 64)->nullable();
            $table->string('move_type', 24);
            $table->string('status', 24)->default('draft');
            $table->foreignUuid('source_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignUuid('destination_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->foreignUuid('related_sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->timestamp('reserved_at')->nullable();
            $table->foreignUuid('reserved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignUuid('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'move_type', 'status']);
            $table->index('related_sales_order_id');
            $table->index('source_location_id');
            $table->index('destination_location_id');
            $table->index('product_id');
            $table->index('created_by');
            $table->index('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_moves');
        Schema::dropIfExists('inventory_stock_levels');
        Schema::dropIfExists('inventory_locations');
        Schema::dropIfExists('inventory_warehouses');
    }
};
