<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_attribute_assignments', function (Blueprint $table): void {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->primary(['product_id', 'attribute_id']);
            $table->index('attribute_id');
        });

        DB::statement(<<<'SQL'
            INSERT INTO product_attribute_assignments (product_id, attribute_id, created_at, updated_at)
            SELECT v.product_id, v.attribute_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            FROM product_attribute_values AS v
            JOIN product_type_attribute AS pta
              ON pta.product_type_id = v.product_type_id AND pta.attribute_id = v.attribute_id
            WHERE pta.is_required = 0
            ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_assignments');
    }
};
