<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('service_level', 24)->default('standard')->after('order_id');
            $table->text('notes')->nullable()->after('tracking_url');
        });

        Schema::table('order_progress_photos', function (Blueprint $table) {
            $table->boolean('is_customer_visible')->default(true)->after('alt_text');
            $table->boolean('notified')->default(false)->after('is_customer_visible');
        });

        Schema::table('garment_types', function (Blueprint $table) {
            $table->string('meta_title')->nullable()->after('description');
            $table->string('meta_description', 320)->nullable()->after('meta_title');
        });

        Schema::table('fabrics', function (Blueprint $table) {
            $table->string('meta_description', 320)->nullable()->after('drape_notes');
        });
    }

    public function down(): void
    {
        Schema::table('fabrics', function (Blueprint $table) {
            $table->dropColumn('meta_description');
        });

        Schema::table('garment_types', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description']);
        });

        Schema::table('order_progress_photos', function (Blueprint $table) {
            $table->dropColumn(['is_customer_visible', 'notified']);
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['service_level', 'notes']);
        });
    }
};
