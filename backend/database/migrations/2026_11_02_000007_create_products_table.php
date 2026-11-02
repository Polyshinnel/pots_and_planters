<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_series', function (Blueprint $table): void {
            $table->unique(['id', 'brand_id'], 'product_series_id_brand_unique');
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_type_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('series_id')->nullable();
            $table->string('sku', 100)->collation('utf8mb4_bin');
            $table->string('name', 255);
            $table->decimal('price_aed', 15, 4)->nullable();
            $table->string('status', 16)->default('draft');
            $table->timestamps();

            $table->unique(['brand_id', 'sku']);
            $table->index(['status', 'updated_at', 'id']);
            $table->foreign(['series_id', 'brand_id'], 'products_series_brand_foreign')
                ->references(['id', 'brand_id'])->on('product_series')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE products ADD CONSTRAINT products_price_aed_positive CHECK (price_aed IS NULL OR price_aed > 0)');
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_status_valid CHECK (status IN ('draft', 'published', 'archived'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('products');

        Schema::table('product_series', function (Blueprint $table): void {
            $table->dropUnique('product_series_id_brand_unique');
        });
    }
};
