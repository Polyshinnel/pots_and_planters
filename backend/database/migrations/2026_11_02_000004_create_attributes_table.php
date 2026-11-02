<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name', 255);
            $table->string('code', 64)->unique();
            $table->string('type', 16);
            $table->string('unit', 32)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index('type');
        });

        DB::statement("ALTER TABLE attributes ADD CONSTRAINT attributes_type_check CHECK (type IN ('text', 'number', 'boolean', 'select', 'multiselect'))");
        DB::statement("ALTER TABLE attributes ADD CONSTRAINT attributes_unit_type_check CHECK (unit IS NULL OR type = 'number')");
    }

    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};
