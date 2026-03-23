<?php

use App\Core\MasterData\Models\Product;
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
        Schema::table('products', function (Blueprint $table) {
            $table->string('tracking_mode', 16)
                ->default(Product::TRACKING_NONE)
                ->after('type');
            $table->index(['company_id', 'type', 'tracking_mode'], 'products_company_type_tracking_index');
        });

        Schema::create('inventory_lots', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('code', 96);
            $table->string('tracking_mode', 16)->default(Product::TRACKING_LOT);
            $table->decimal('quantity_on_hand', 15, 4)->default(0);
            $table->decimal('quantity_reserved', 15, 4)->default(0);
            $table->timestamp('received_at')->nullable();
            $table->timestamp('last_moved_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'location_id', 'product_id', 'code'], 'inventory_lots_company_location_product_code_unique');
            $table->index(['company_id', 'product_id']);
            $table->index(['company_id', 'location_id']);
            $table->index(['company_id', 'tracking_mode']);
            $table->index(['company_id', 'code']);
            $table->index('created_by');
            $table->index('updated_by');
        });

        Schema::create('inventory_stock_move_lines', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('stock_move_id')->constrained('inventory_stock_moves')->cascadeOnDelete();
            $table->foreignUuid('source_lot_id')->nullable()->constrained('inventory_lots')->nullOnDelete();
            $table->foreignUuid('resulting_lot_id')->nullable()->constrained('inventory_lots')->nullOnDelete();
            $table->string('lot_code', 96)->nullable();
            $table->decimal('quantity', 15, 4);
            $table->unsignedInteger('sequence')->default(1);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'stock_move_id']);
            $table->index(['company_id', 'source_lot_id']);
            $table->index(['company_id', 'resulting_lot_id']);
            $table->index(['company_id', 'lot_code']);
            $table->index('created_by');
            $table->index('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_move_lines');
        Schema::dropIfExists('inventory_lots');

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_company_type_tracking_index');
            $table->dropColumn('tracking_mode');
        });
    }
};
