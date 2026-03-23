<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_cycle_counts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 64);
            $table->foreignUuid('warehouse_id')->nullable()->constrained('inventory_warehouses')->nullOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->string('status', 24)->default('draft');
            $table->unsignedInteger('line_count')->default(0);
            $table->decimal('total_expected_quantity', 15, 4)->default(0);
            $table->decimal('total_counted_quantity', 15, 4)->default(0);
            $table->decimal('total_variance_quantity', 15, 4)->default(0);
            $table->decimal('total_absolute_variance_quantity', 15, 4)->default(0);
            $table->decimal('total_variance_value', 15, 2)->default(0);
            $table->decimal('total_absolute_variance_value', 15, 2)->default(0);
            $table->boolean('requires_approval')->default(false);
            $table->string('approval_status', 24)->default('not_required');
            $table->timestamp('started_at')->nullable();
            $table->foreignUuid('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignUuid('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignUuid('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'reference']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'approval_status']);
            $table->index('warehouse_id');
            $table->index('location_id');
        });

        Schema::create('inventory_cycle_count_lines', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('cycle_count_id')->constrained('inventory_cycle_counts')->cascadeOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('lot_id')->nullable()->constrained('inventory_lots')->nullOnDelete();
            $table->foreignUuid('adjustment_move_id')->nullable()->constrained('inventory_stock_moves')->nullOnDelete();
            $table->string('tracking_mode', 24)->default('none');
            $table->string('lot_code', 96)->nullable();
            $table->decimal('expected_quantity', 15, 4)->default(0);
            $table->decimal('counted_quantity', 15, 4)->nullable();
            $table->decimal('variance_quantity', 15, 4)->default(0);
            $table->decimal('estimated_unit_cost', 15, 2)->default(0);
            $table->decimal('variance_value', 15, 2)->default(0);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'cycle_count_id']);
            $table->index(['company_id', 'location_id']);
            $table->index(['company_id', 'product_id']);
            $table->index('lot_id');
            $table->index('adjustment_move_id');
        });

        Schema::table('inventory_stock_moves', function (Blueprint $table) {
            $table->foreignUuid('cycle_count_id')
                ->nullable()
                ->after('company_id')
                ->constrained('inventory_cycle_counts')
                ->nullOnDelete();

            $table->index('cycle_count_id');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_stock_moves', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cycle_count_id');
        });

        Schema::dropIfExists('inventory_cycle_count_lines');
        Schema::dropIfExists('inventory_cycle_counts');
    }
};
