<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);        // hero|slider|carousel|video_feature|lookbook_grid|testimonial|promo_banner
            $table->string('placement', 40);   // homepage|garment_page|lookbook
            $table->string('title')->nullable();
            $table->json('payload')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['placement', 'is_active', 'sort_order']);
        });

        Schema::create('cms_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cms_block_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('kind', 16)->default('image'); // image | video
            $table->string('alt_text');                    // required before publish
            $table->string('caption')->nullable();
            $table->string('link_url')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('derivatives')->nullable();
            $table->string('blurhash', 64)->nullable();
            $table->timestamps();

            $table->index('cms_block_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_media');
        Schema::dropIfExists('cms_blocks');
    }
};
