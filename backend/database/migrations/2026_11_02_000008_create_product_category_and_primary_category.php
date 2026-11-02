<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_category', function (Blueprint $table): void {
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('category_id');
            $table->primary(['product_id', 'category_id']);
            $table->index('category_id');
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedBigInteger('primary_category_id')->nullable()->after('series_id');
            $table->foreign(['id', 'primary_category_id'], 'products_primary_category_membership_foreign')
                ->references(['product_id', 'category_id'])->on('product_category')->restrictOnDelete();
        });

    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign('products_primary_category_membership_foreign');
            $table->dropColumn('primary_category_id');
        });

        Schema::dropIfExists('product_category');
    }
};
