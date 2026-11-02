<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('disk', 32);
            $table->string('path', 512);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();
            $table->unique(['disk', 'path'], 'media_assets_disk_path_unique');
        });

        Schema::create('product_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('media_asset_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('alt_override', 255)->nullable();
            $table->boolean('is_main')->default(false);
            $table->unsignedBigInteger('main_product_id')->nullable()->storedAs('CASE WHEN is_main = 1 THEN product_id ELSE NULL END');
            $table->timestamps();
            $table->unique(['product_id', 'media_asset_id'], 'product_images_product_asset_unique');
            $table->unique('main_product_id', 'product_images_one_main_per_product_unique');
            $table->index(['product_id', 'sort_order', 'id'], 'product_images_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('media_assets');
    }
};
