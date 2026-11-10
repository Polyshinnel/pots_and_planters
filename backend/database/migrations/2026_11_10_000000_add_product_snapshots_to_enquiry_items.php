<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiry_items', function (Blueprint $table): void {
            $table->uuid('product_public_id')->nullable()->after('product_identifier');
            $table->uuid('brand_public_id')->nullable()->after('product_public_id');
            $table->string('brand_name_snapshot', 255)->nullable()->after('sku_snapshot');
            $table->decimal('unit_price_aed', 15, 4)->nullable()->after('quantity');
            $table->char('currency', 3)->nullable()->after('unit_price_aed');
            $table->boolean('availability_snapshot')->nullable()->after('currency');
            $table->timestamp('snapshot_at')->nullable()->after('availability_snapshot');
            $table->json('product_snapshot')->nullable()->after('snapshot_at');
            $table->index('product_public_id');
        });
    }

    public function down(): void
    {
        Schema::table('enquiry_items', function (Blueprint $table): void {
            $table->dropIndex(['product_public_id']);
            $table->dropColumn([
                'product_public_id', 'brand_public_id', 'brand_name_snapshot', 'unit_price_aed',
                'currency', 'availability_snapshot', 'snapshot_at', 'product_snapshot',
            ]);
        });
    }
};
