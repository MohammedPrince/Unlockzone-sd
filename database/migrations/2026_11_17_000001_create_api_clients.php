<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * This server acting as an API provider: other servers (DHRU Fusion compatible) connect to it
 * with a customer's username and an API key, and order that customer's services with its credit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $table) {
            $table->id();
            // One API access per customer account; orders are charged to this customer's wallet.
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            // SHA-256 of the key: the key itself is shown once when it is made and never stored.
            $table->char('api_key_hash', 64)->unique();
            $table->string('api_key_hint', 12);
            // all = every service on sale; selected = only the services in api_client_services.
            $table->enum('access', ['all', 'selected'])->default('all');
            $table->enum('status', ['active', 'suspended'])->default('active')->index();
            // Optional allow-list, one address per line; empty means any address.
            $table->text('allowed_ips')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('api_client_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_client_id')->constrained('api_clients')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->unique(['api_client_id', 'service_id']);
        });

        // Orders that came in through the API, so the admin can tell them apart.
        Schema::table('service_requests', function (Blueprint $table) {
            $table->foreignId('api_client_id')->nullable()->after('provider_checked_at')->constrained('api_clients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('api_client_id');
        });
        Schema::dropIfExists('api_client_services');
        Schema::dropIfExists('api_clients');
    }
};
