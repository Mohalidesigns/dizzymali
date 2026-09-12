<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 24)->unique();          // DZM-2609-A7K3
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('draft');
            $table->unsignedTinyInteger('wizard_step')->default(1);

            // Money. Every one of these is integer kobo. No exceptions.
            $table->unsignedBigInteger('fabric_total_kobo')->default(0);
            $table->unsignedBigInteger('sewing_total_kobo')->default(0);
            $table->unsignedBigInteger('options_total_kobo')->default(0);
            $table->unsignedBigInteger('subtotal_kobo')->default(0);
            $table->unsignedBigInteger('shipping_kobo')->default(0);
            $table->unsignedBigInteger('discount_kobo')->default(0);
            $table->unsignedBigInteger('tax_kobo')->default(0);
            $table->unsignedBigInteger('total_kobo')->default(0);
            $table->unsignedBigInteger('amount_paid_kobo')->default(0);

            // Display currency is frozen at checkout along with the rate used.
            $table->string('currency_code', 3)->default('NGN');
            $table->decimal('fx_rate_used', 18, 8)->nullable();
            $table->decimal('fx_margin_percent', 5, 2)->nullable();
            $table->unsignedBigInteger('display_total_minor')->nullable();

            $table->string('service_level', 24)->default('standard');
            $table->foreignId('shipping_address_id')->nullable()->constrained('addresses')->nullOnDelete();
            $table->json('shipping_address_snapshot')->nullable();
            $table->foreignId('shipping_zone_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedTinyInteger('deposit_percent')->nullable();
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->date('promised_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index('status');
            $table->index('placed_at');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('garment_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('fabric_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('yards_required', 5, 2)->default(0);
            $table->decimal('yards_base', 5, 2)->default(0);
            $table->decimal('yards_size_adjustment', 5, 2)->default(0);
            $table->decimal('yards_customer_extra', 5, 2)->default(0);

            $table->foreignId('measurement_profile_id')->nullable()->constrained()->nullOnDelete();
            // Frozen copy. A profile edited next year must not change a garment already cut.
            $table->json('measurement_snapshot')->nullable();
            $table->json('garment_type_snapshot')->nullable();
            $table->json('fabric_variant_snapshot')->nullable();

            $table->unsignedBigInteger('fabric_cost_kobo')->default(0);
            $table->unsignedBigInteger('sewing_cost_kobo')->default(0);
            $table->unsignedBigInteger('options_cost_kobo')->default(0);
            $table->unsignedBigInteger('line_total_kobo')->default(0);

            $table->text('style_notes')->nullable();
            $table->foreignId('tailor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('pending');
            $table->timestamps();

            $table->index('order_id');
        });

        Schema::create('order_item_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('garment_option_id')->nullable()->constrained()->nullOnDelete();
            $table->string('group_name');
            $table->string('option_name');
            $table->unsignedBigInteger('surcharge_kobo')->default(0);
            $table->decimal('additional_yards', 4, 2)->default(0);
            $table->timestamps();

            $table->index('order_item_id');
        });

        Schema::create('order_item_inspirations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 64)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('source_url')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('order_item_id');
        });

        Schema::create('order_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->boolean('is_customer_visible')->default(true);
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
        });

        Schema::create('order_progress_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stage', 32);
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('caption')->nullable();
            $table->string('alt_text');
            $table->timestamps();

            $table->index('order_id');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 24);                    // paystack | flutterwave | manual
            $table->string('gateway_reference')->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->string('status', 24)->default('pending');  // pending | success | failed | refunded
            $table->string('kind', 16)->default('full');       // full | deposit | balance
            $table->unsignedBigInteger('amount_kobo');
            $table->string('currency_code', 3)->default('NGN');
            $table->unsignedBigInteger('charged_minor')->nullable();
            $table->decimal('fx_rate_used', 18, 8)->nullable();
            $table->json('gateway_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index('gateway_reference');
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('carrier', 40)->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('tracking_url')->nullable();
            $table->unsignedInteger('weight_grams')->nullable();
            $table->string('hs_code', 16)->nullable();
            $table->unsignedBigInteger('declared_value_kobo')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_progress_photos');
        Schema::dropIfExists('order_status_events');
        Schema::dropIfExists('order_item_inspirations');
        Schema::dropIfExists('order_item_options');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
