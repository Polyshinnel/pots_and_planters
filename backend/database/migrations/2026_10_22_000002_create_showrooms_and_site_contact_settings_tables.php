<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('showrooms', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->unique();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->json('phones')->nullable();
            $table->json('emails')->nullable();
            $table->string('instagram_handle')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['status', 'sort_order', 'id']);
        });

        Schema::create('site_contact_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->foreignId('primary_showroom_id')->nullable()->constrained('showrooms')->nullOnDelete();
            $table->text('whatsapp_url')->nullable();
            $table->timestamps();
        });
        DB::table('site_contact_settings')->insert(['id' => 1, 'primary_showroom_id' => null, 'whatsapp_url' => null, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('site_contact_settings');
        Schema::dropIfExists('showrooms');
    }
};
