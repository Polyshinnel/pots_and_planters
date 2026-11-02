<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->json('card_display_attribute_ids')->nullable();
        });

        Schema::table('attributes', function (Blueprint $table): void {
            $table->string('short_label', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table): void {
            $table->dropColumn('short_label');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('card_display_attribute_ids');
        });
    }
};
