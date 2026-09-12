<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->after('email');
            $table->string('country_code', 2)->nullable()->after('phone');
            $table->string('preferred_currency', 3)->default('NGN')->after('country_code');
            $table->string('unit_preference', 2)->default('in')->after('preferred_currency');
            $table->boolean('whatsapp_opt_in')->default(false)->after('unit_preference');
            $table->boolean('marketing_opt_in')->default(false)->after('whatsapp_opt_in');
            $table->timestamp('privacy_consented_at')->nullable()->after('marketing_opt_in');
            $table->timestamp('last_seen_at')->nullable()->after('privacy_consented_at');
            $table->softDeletes();
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('recipient_name');
            $table->string('phone', 32);
            $table->string('line_1');
            $table->string('line_2')->nullable();
            $table->string('city');
            $table->string('state_region')->nullable();
            $table->string('postcode', 24)->nullable();
            $table->string('country_code', 2);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_default']);
        });

        Schema::create('measurement_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');                            // "My kaftan fit"
            $table->string('unit_preference', 2)->default('in');
            $table->string('source', 24)->default('manual');   // manual | uploaded | tailor_assisted
            $table->string('review_status', 24)->default('ready'); // ready | pending_review | rejected
            $table->text('notes')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_default']);
            $table->index('review_status');
        });

        Schema::create('measurement_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('measurement_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('measurement_field_id')->constrained()->cascadeOnDelete();
            $table->decimal('value_inches', 5, 2);             // one canonical unit, always
            $table->timestamps();

            $table->unique(['measurement_profile_id', 'measurement_field_id'], 'mp_mf_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurement_values');
        Schema::dropIfExists('measurement_profiles');
        Schema::dropIfExists('addresses');

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'phone', 'country_code', 'preferred_currency', 'unit_preference',
                'whatsapp_opt_in', 'marketing_opt_in', 'privacy_consented_at', 'last_seen_at',
            ]);
        });
    }
};
