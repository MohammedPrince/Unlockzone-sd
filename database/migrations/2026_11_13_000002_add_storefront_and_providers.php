<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storefront extras (favourites, ticker bar, service suspensions) and
 * connections to other service providers' APIs (DHRU Fusion compatible).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Suspended from ordering every service, while the account itself still signs in.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('services_suspended')->default(false)->after('status');
        });

        // Suspended from one specific service.
        Schema::create('service_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'service_id']);
        });

        // "Want to use frequently": a customer's pinned services.
        Schema::create('service_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'service_id']);
        });

        // Messages in the scrolling bar at the top of the customer site.
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('text', 200);
            $table->string('text_ar', 200)->nullable();
            $table->string('icon', 16)->nullable();
            $table->string('color', 20)->default('default');
            $table->string('link', 255)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('api_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('type', 30)->default('dhru');
            $table->string('url', 255);
            $table->string('username', 150);
            // Encrypted with APP_KEY by the model cast.
            $table->text('api_key');
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->decimal('balance', 14, 2)->nullable();
            $table->string('currency', 10)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('services_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        // A copy of each provider's price list, refreshed with "Sync services".
        Schema::create('provider_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_provider_id')->constrained('api_providers')->cascadeOnDelete();
            $table->string('remote_id', 40);
            $table->string('group_name', 191)->nullable();
            $table->string('type', 20)->nullable();
            $table->string('name', 255);
            $table->decimal('price', 14, 4)->default(0);
            $table->string('delivery_time', 100)->nullable();
            // [{name, type, required}] custom fields the provider asks for.
            $table->json('fields')->nullable();
            $table->boolean('needs_imei')->default(true);
            $table->timestamps();
            $table->unique(['api_provider_id', 'remote_id']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('api_provider_id')->nullable()->after('configuration')->constrained('api_providers')->nullOnDelete();
            $table->string('remote_service_id', 40)->nullable()->after('api_provider_id');
        });

        Schema::table('service_requests', function (Blueprint $table) {
            $table->foreignId('api_provider_id')->nullable()->after('processed_by')->constrained('api_providers')->nullOnDelete();
            $table->string('remote_order_id', 60)->nullable()->after('api_provider_id')->index();
            // sent, error, done
            $table->string('provider_status', 20)->nullable()->after('remote_order_id');
            $table->text('provider_message')->nullable()->after('provider_status');
            $table->timestamp('provider_checked_at')->nullable()->after('provider_message');
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('api_provider_id');
            $table->dropColumn(['remote_order_id', 'provider_status', 'provider_message', 'provider_checked_at']);
        });
        Schema::table('services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('api_provider_id');
            $table->dropColumn('remote_service_id');
        });
        Schema::dropIfExists('provider_services');
        Schema::dropIfExists('api_providers');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('service_favorites');
        Schema::dropIfExists('service_blocks');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('services_suspended');
        });
    }
};
