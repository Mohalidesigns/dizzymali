<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');                 // garment type, fabric variant, cms block...
            $table->string('collection', 40)->default('default'); // hero | swatch | gallery | lookbook
            $table->string('disk', 32)->default('public');
            $table->string('path');
            $table->string('kind', 16)->default('image'); // image | video
            $table->string('mime_type', 64)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            // Alt text is required before publish. An image nobody can describe
            // is an image half our customers cannot use.
            $table->string('alt_text');
            $table->string('caption')->nullable();

            // Populated by the queued derivative job: {"avif":{"640":"path"},...}
            $table->json('derivatives')->nullable();
            $table->string('blurhash', 64)->nullable();
            $table->string('dominant_hex', 7)->nullable();
            $table->string('processing_status', 24)->default('pending'); // pending|processing|ready|failed
            $table->text('processing_error')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id', 'collection'], 'media_attachable_collection_idx');
            $table->index('processing_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
