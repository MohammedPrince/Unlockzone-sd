<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Web Owner prices: a second special price level next to VIP, with its own percentage
 * ("owner_price_adjust" setting) and fixed price per service. A customer is VIP or Web Owner, never both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_web_owner')->default(false)->after('is_vip');
        });
        Schema::table('services', function (Blueprint $table) {
            $table->decimal('owner_price', 14, 2)->nullable()->after('vip_price');
        });

        // Customers already tagged "Web Owner" get Web Owner prices (and stop being VIP: one level only).
        DB::table('users')->where('role', 'customer')
            ->whereRaw('LOWER(TRIM(customer_tag)) = ?', ['web owner'])
            ->update(['is_web_owner' => true, 'is_vip' => false]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_web_owner'));
        Schema::table('services', fn (Blueprint $table) => $table->dropColumn('owner_price'));
    }
};
