<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Customers already tagged "VIP" get VIP prices: from now on the tag and VIP prices go together. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'customer')
            ->whereRaw('LOWER(TRIM(customer_tag)) = ?', ['vip'])
            ->update(['is_vip' => true]);
    }

    public function down(): void
    {
        // Nothing to undo: is_vip itself is removed by the previous migration's down().
    }
};
