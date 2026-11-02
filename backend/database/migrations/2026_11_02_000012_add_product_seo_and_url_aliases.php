<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('slug')->nullable()->collation('utf8mb4_bin')->unique('products_slug_unique');
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->foreignId('seo_og_media_asset_id')->nullable()->constrained('media_assets')->restrictOnDelete();
        });

        Schema::create('product_url_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path', 600)->collation('utf8mb4_bin')->unique('product_url_aliases_path_unique');
            $table->timestamps();
            $table->index(['product_id', 'id'], 'product_url_aliases_product_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_url_aliases');
        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('seo_og_media_asset_id');
            $table->dropUnique('products_slug_unique');
            $table->dropColumn(['slug', 'seo_title', 'seo_description']);
        });
    }
};
