<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE attributes DROP CONSTRAINT attributes_unit_type_check');
        DB::table('attributes')->whereNull('unit')->update(['unit' => 'cm']);

        Schema::table('attributes', function (Blueprint $table): void {
            $table->string('unit', 32)->default('cm')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        DB::table('attributes')->where('type', '!=', 'number')->update(['unit' => null]);

        Schema::table('attributes', function (Blueprint $table): void {
            $table->string('unit', 32)->nullable()->default(null)->change();
        });

        DB::statement("ALTER TABLE attributes ADD CONSTRAINT attributes_unit_type_check CHECK (unit IS NULL OR type = 'number')");
    }
};
