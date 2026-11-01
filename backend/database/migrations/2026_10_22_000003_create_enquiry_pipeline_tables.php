<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->boolean('notifications_enabled')->default(false);
            $table->text('bot_token')->nullable(); // Laravel encrypted cast; never plaintext after model persistence.
            $table->boolean('proxy_enabled')->default(false);
            $table->string('proxy_host', 253)->nullable();
            $table->unsignedSmallInteger('proxy_port')->nullable();
            $table->string('proxy_username')->nullable();
            $table->text('proxy_password')->nullable(); // Laravel encrypted cast.
            $table->timestamps();
        });

        Schema::create('telegram_recipients', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->bigInteger('chat_id')->unique(); // Signed: Telegram group IDs are negative.
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('enquiries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('type', 32)->index();
            $table->string('status', 24)->default('new');
            $table->string('name', 200);
            $table->string('phone', 64);
            $table->string('email', 254);
            $table->string('company', 200)->nullable();
            $table->text('message')->nullable();
            $table->string('role', 40)->nullable();
            $table->string('role_other', 200)->nullable();
            $table->foreignId('catalogue_id')->nullable()->constrained()->nullOnDelete();
            $table->json('catalogue_snapshot')->nullable();
            $table->string('source_path', 2048)->nullable();
            $table->string('source_placement', 200)->nullable();
            $table->json('technical_metadata')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->index(['status', 'submitted_at']);
            $table->index(['type', 'submitted_at']);
        });

        Schema::create('enquiry_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enquiry_id')->constrained()->cascadeOnDelete();
            // BE-08 product FK/snapshot fields will be added with the authoritative product catalogue.
            $table->string('product_identifier', 100);
            $table->string('sku_snapshot', 100);
            $table->unsignedInteger('quantity');
            $table->unsignedSmallInteger('position');
            $table->timestamps();
            $table->unique(['enquiry_id', 'position']);
        });

        Schema::create('enquiry_outbox_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enquiry_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('pending')->index();
            $table->boolean('notifications_enabled_snapshot')->default(false);
            $table->timestamp('available_at')->nullable()->index();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('enquiry_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('outbox_event_id')->constrained('enquiry_outbox_events')->cascadeOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('telegram_recipients')->nullOnDelete();
            $table->bigInteger('chat_id_snapshot');
            $table->string('recipient_name_snapshot', 120);
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->string('telegram_message_id', 64)->nullable();
            $table->string('last_error_code', 80)->nullable();
            $table->text('safe_error')->nullable();
            $table->timestamps();
            $table->unique(['outbox_event_id', 'chat_id_snapshot']);
            $table->index(['status', 'next_attempt_at']);
        });

        Schema::create('enquiry_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->char('key_hash', 64)->unique();
            $table->char('payload_hash', 64);
            $table->foreignId('enquiry_id')->constrained()->cascadeOnDelete();
            $table->string('notification_status', 32);
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiry_idempotency_keys');
        Schema::dropIfExists('enquiry_deliveries');
        Schema::dropIfExists('enquiry_outbox_events');
        Schema::dropIfExists('enquiry_items');
        Schema::dropIfExists('enquiries');
        Schema::dropIfExists('telegram_recipients');
        Schema::dropIfExists('telegram_settings');
    }
};
