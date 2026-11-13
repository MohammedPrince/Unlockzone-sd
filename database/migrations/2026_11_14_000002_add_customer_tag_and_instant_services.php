<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A label the admin gives a customer, e.g. "VIP". Shown before their email in the customer menu.
        Schema::table('users', function (Blueprint $table) {
            $table->string('customer_tag', 30)->nullable()->after('status');
        });

        // Services the admin marks as delivered instantly get a yellow "Instant" tag.
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('is_instant')->default(false)->after('delivery_time');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('customer_tag'));
        Schema::table('services', fn (Blueprint $table) => $table->dropColumn('is_instant'));
    }
};
