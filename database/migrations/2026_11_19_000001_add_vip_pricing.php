<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VIP pricing: customers the admin marks as VIP pay VIP prices.
 * A service's VIP price is its fixed vip_price when set, otherwise the site price with the
 * "vip_price_adjust" setting (a percentage, e.g. -10 = 10% discount) applied.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_vip')->default(false)->after('customer_tag');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->decimal('vip_price', 14, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_vip'));
        Schema::table('services', fn (Blueprint $table) => $table->dropColumn('vip_price'));
    }
};
