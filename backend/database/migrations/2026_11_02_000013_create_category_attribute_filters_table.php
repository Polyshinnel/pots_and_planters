<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_attribute_filters', function (Blueprint $table): void {
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('attribute_id')->constrained('attributes')->restrictOnDelete();
            $table->string('display_type', 24);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->primary(['category_id', 'attribute_id']);
            $table->index(['category_id', 'sort_order', 'attribute_id'], 'caf_category_order_index');
        });

        DB::statement("ALTER TABLE category_attribute_filters ADD CONSTRAINT category_attribute_filters_display_type_check CHECK (display_type IN ('values', 'range', 'boolean', 'options', 'multi_options'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('category_attribute_filters');
    }
};
