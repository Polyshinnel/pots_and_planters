<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unique(['id', 'product_type_id'], 'products_id_type_unique');
        });

        Schema::table('attribute_options', function (Blueprint $table): void {
            $table->unique(['id', 'attribute_id'], 'attribute_options_id_attribute_unique');
        });

        Schema::create('product_attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_type_id');
            $table->foreignId('attribute_id')->constrained()->restrictOnDelete();
            $table->text('text_value')->nullable();
            $table->decimal('number_value', 20, 6)->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'attribute_id'], 'product_attribute_values_product_attribute_unique');
            $table->unique(['id', 'attribute_id'], 'product_attribute_values_id_attribute_unique');
            $table->foreign(['product_id', 'product_type_id'], 'product_attribute_values_product_type_foreign')
                ->references(['id', 'product_type_id'])->on('products')->restrictOnDelete();
            $table->foreign(['product_type_id', 'attribute_id'], 'product_attribute_values_type_attribute_foreign')
                ->references(['product_type_id', 'attribute_id'])->on('product_type_attribute')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE product_attribute_values ADD CONSTRAINT product_attribute_values_single_scalar_check CHECK ((text_value IS NOT NULL) + (number_value IS NOT NULL) + (boolean_value IS NOT NULL) <= 1)');

        Schema::create('product_attribute_selections', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_attribute_value_id');
            $table->unsignedBigInteger('attribute_id');
            $table->unsignedBigInteger('attribute_option_id');
            $table->timestamps();
            $table->unique(['product_attribute_value_id', 'attribute_option_id'], 'product_attribute_selections_value_option_unique');
            $table->foreign(['product_attribute_value_id', 'attribute_id'], 'product_attribute_selections_value_attribute_foreign')
                ->references(['id', 'attribute_id'])->on('product_attribute_values')->cascadeOnDelete();
            $table->foreign(['attribute_option_id', 'attribute_id'], 'product_attribute_selections_option_attribute_foreign')
                ->references(['id', 'attribute_id'])->on('attribute_options')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_selections');
        Schema::dropIfExists('product_attribute_values');

        if ($this->hasIndex('attribute_options', 'attribute_options_id_attribute_unique')) {
            Schema::table('attribute_options', function (Blueprint $table): void {
                $table->dropUnique('attribute_options_id_attribute_unique');
            });
        }

        if ($this->hasIndex('products', 'products_id_type_unique')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropUnique('products_id_type_unique');
            });
        }
    }

    private function hasIndex(string $table, string $name): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}`"))->contains(fn (object $index): bool => $index->Key_name === $name);
    }
};
