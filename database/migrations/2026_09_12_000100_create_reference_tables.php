<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();          // NGN, USD, GBP, EUR
            $table->string('name');
            $table->string('symbol', 8);
            $table->unsignedTinyInteger('decimals')->default(2);
            $table->unsignedInteger('rounding_minor')->default(1); // round display up to nearest N minor units
            $table->boolean('is_base')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('fx_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('currency_id')->constrained()->cascadeOnDelete();
            // units of the target currency per 1 NGN, e.g. GBP 0.000512
            $table->decimal('rate', 18, 8);
            $table->decimal('margin_percent', 5, 2)->default(0); // admin margin on top
            $table->string('source', 40)->default('manual');
            $table->timestamp('effective_at');
            $table->timestamps();

            $table->index(['currency_id', 'effective_at']);
        });

        Schema::create('fabric_materials', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('measurement_fields', function (Blueprint $table) {
            $table->id();
            $table->string('group', 16);                 // top | trouser
            $table->string('key')->unique();             // chest, shoulder, tommy...
            $table->string('label');
            $table->text('help_text')->nullable();
            $table->string('unit_type', 16)->default('circumference'); // length | circumference
            $table->decimal('min_inches', 5, 2);
            $table->decimal('max_inches', 5, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('group');
        });

        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->json('country_codes');               // ["GB"], ["NG"], ...
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();
            $table->string('service_level', 24);         // standard | express
            $table->unsignedInteger('min_grams')->default(0);
            $table->unsignedInteger('max_grams');
            $table->unsignedBigInteger('price_kobo');
            $table->unsignedSmallInteger('transit_days_min');
            $table->unsignedSmallInteger('transit_days_max');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['shipping_zone_id', 'service_level']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->string('group', 40)->default('general');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('shipping_rates');
        Schema::dropIfExists('shipping_zones');
        Schema::dropIfExists('measurement_fields');
        Schema::dropIfExists('fabric_materials');
        Schema::dropIfExists('fx_rates');
        Schema::dropIfExists('currencies');
    }
};
