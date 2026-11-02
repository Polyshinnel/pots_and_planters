<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table): void {
            $table->boolean('is_filterable')->default(false)->after('is_active');
        });

        DB::statement('UPDATE attributes AS a SET a.is_filterable = EXISTS (SELECT 1 FROM product_type_attribute AS pta WHERE pta.attribute_id = a.id AND pta.is_filterable = 1)');

        Schema::table('product_type_attribute', function (Blueprint $table): void {
            $table->dropColumn('is_filterable');
        });
    }

    public function down(): void
    {
        Schema::table('product_type_attribute', function (Blueprint $table): void {
            $table->boolean('is_filterable')->default(false)->after('is_required');
        });

        DB::statement('UPDATE product_type_attribute AS pta JOIN attributes AS a ON a.id = pta.attribute_id SET pta.is_filterable = a.is_filterable');

        Schema::table('attributes', function (Blueprint $table): void {
            $table->dropColumn('is_filterable');
        });
    }
};
