<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_type_attribute', function (Blueprint $table): void {
            $table->foreignId('product_type_id')->constrained('product_types')->restrictOnDelete();
            $table->foreignId('attribute_id')->constrained('attributes')->restrictOnDelete();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_filterable')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->primary(['product_type_id', 'attribute_id']);
            $table->index(['product_type_id', 'sort_order', 'attribute_id'], 'pta_type_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_type_attribute');
    }
};
