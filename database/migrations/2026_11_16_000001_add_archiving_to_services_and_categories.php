<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting a category archives it (and its services that have orders) instead of removing the rows,
 * so customers still see those orders, greyed out, in their order history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_categories', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('services', fn (Blueprint $table) => $table->softDeletes());
    }

    public function down(): void
    {
        Schema::table('services', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('service_categories', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};
