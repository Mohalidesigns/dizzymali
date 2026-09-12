<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garment_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('tagline')->nullable();        // "the occasion it suits"
            $table->text('description')->nullable();
            $table->unsignedBigInteger('base_sewing_cost_kobo');
            $table->decimal('default_yardage', 4, 2);
            $table->unsignedInteger('base_weight_grams')->default(600);
            $table->unsignedInteger('grams_per_yard')->default(220);
            $table->boolean('requires_top_measurements')->default(true);
            $table->boolean('requires_trouser_measurements')->default(false);
            $table->unsignedSmallInteger('lead_time_days')->default(21);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('garment_type_measurement_field', function (Blueprint $table) {
            $table->id();
            $table->foreignId('garment_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('measurement_field_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->unique(['garment_type_id', 'measurement_field_id'], 'gt_mf_unique');
        });

        // Admin-editable rule: chest > 46in on an Agbada adds 0.50 yards.
        Schema::create('yardage_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('garment_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('measurement_field_id')->constrained()->cascadeOnDelete();
            $table->decimal('threshold_inches', 5, 2);
            $table->decimal('additional_yards', 4, 2);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['garment_type_id', 'is_active']);
        });

        Schema::create('fabrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fabric_material_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('care_instructions')->nullable();
            $table->text('drape_notes')->nullable();
            $table->string('origin')->nullable();
            $table->unsignedSmallInteger('gsm')->nullable();
            $table->decimal('width_inches', 5, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('fabric_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fabric_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('colour_name');
            $table->string('colour_hex', 7)->nullable();
            $table->string('pattern')->nullable();
            $table->unsignedBigInteger('price_per_yard_kobo');
            $table->decimal('stock_yards', 8, 2)->default(0);
            $table->decimal('reserved_yards', 8, 2)->default(0);
            $table->decimal('low_stock_threshold_yards', 8, 2)->default(10);
            $table->decimal('min_order_yards', 4, 2)->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['fabric_id', 'is_active']);
        });

        // Collar style, embroidery level, lining, pockets...
        Schema::create('garment_option_groups', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('help_text')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('allows_multiple')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('garment_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('garment_option_group_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('surcharge_kobo')->default(0);
            $table->decimal('additional_yards', 4, 2)->default(0);
            $table->unsignedSmallInteger('additional_lead_days')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['garment_option_group_id', 'slug'], 'gog_slug_unique');
        });

        Schema::create('garment_type_option_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('garment_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('garment_option_group_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->unique(['garment_type_id', 'garment_option_group_id'], 'gt_gog_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garment_type_option_group');
        Schema::dropIfExists('garment_options');
        Schema::dropIfExists('garment_option_groups');
        Schema::dropIfExists('fabric_variants');
        Schema::dropIfExists('fabrics');
        Schema::dropIfExists('yardage_rules');
        Schema::dropIfExists('garment_type_measurement_field');
        Schema::dropIfExists('garment_types');
    }
};
