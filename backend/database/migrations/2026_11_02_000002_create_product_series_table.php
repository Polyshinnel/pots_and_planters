<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_series', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->string('name', 255);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index('brand_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_series');
    }
};
