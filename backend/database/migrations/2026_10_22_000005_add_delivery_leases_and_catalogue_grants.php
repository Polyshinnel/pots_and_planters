<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiry_deliveries', function (Blueprint $table): void {
            $table->uuid('lease_token')->nullable()->after('status');
            $table->timestamp('lease_expires_at')->nullable()->index()->after('lease_token');
            $table->timestamp('dispatch_after_at')->nullable()->index()->after('lease_expires_at');
        });

        Schema::create('catalogue_download_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalogue_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamps();
            $table->index('enquiry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogue_download_grants');
        Schema::table('enquiry_deliveries', function (Blueprint $table): void {
            $table->dropIndex(['lease_expires_at']);
            $table->dropIndex(['dispatch_after_at']);
            $table->dropColumn(['lease_token', 'lease_expires_at', 'dispatch_after_at']);
        });
    }
};
