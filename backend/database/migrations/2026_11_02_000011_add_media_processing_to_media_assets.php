<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            // Existing files stay pending, without a token or an automatic dispatch.
            $table->string('processing_status', 16)->default('pending')->index();
            $table->uuid('processing_token')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processing_finished_at')->nullable();
            $table->string('processing_error', 255)->nullable();
            $table->json('variants')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropIndex(['processing_status']);
            $table->dropColumn(['processing_status', 'processing_token', 'queued_at', 'processing_started_at', 'processing_finished_at', 'processing_error', 'variants']);
        });
    }
};
