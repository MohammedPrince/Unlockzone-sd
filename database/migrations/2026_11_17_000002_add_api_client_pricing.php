<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What each API client pays: a percentage on the site price, and optional fixed prices per service. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_clients', function (Blueprint $table) {
            // +10 = 10% above the site price, -5 = 5% discount, 0 = site price.
            $table->decimal('price_adjust', 7, 2)->default(0)->after('access');
        });

        // A fixed price for one service for one client; wins over price_adjust.
        Schema::create('api_client_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_client_id')->constrained('api_clients')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->decimal('price', 14, 2);
            $table->unique(['api_client_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_client_prices');
        Schema::table('api_clients', function (Blueprint $table) {
            $table->dropColumn('price_adjust');
        });
    }
};
