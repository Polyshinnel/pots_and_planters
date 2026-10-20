<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 64)->unique();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('heading');
            $table->text('description');
            $table->text('tile_description');
            $table->string('intro_heading')->nullable();
            $table->json('intro_paragraphs')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_alt')->nullable();
            $table->string('seo_title');
            $table->text('seo_description');
            $table->string('og_image_path')->nullable();
            $table->string('status', 16)->default('draft')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('categories'); }
};
