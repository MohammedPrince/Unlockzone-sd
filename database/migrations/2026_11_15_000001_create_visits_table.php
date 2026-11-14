<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per visit (a browser session) to the customer site, with where the visitor came from.
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            // Random id kept in a cookie, so the same person on later days counts as one unique visitor. No IP is stored.
            $table->string('visitor_id', 40)->index();
            $table->string('source', 30)->index();
            $table->string('referrer_host', 120)->nullable();
            $table->string('landing_path', 191)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        // Where a customer came from when they signed up (null for customers the admin created).
        Schema::table('users', function (Blueprint $table) {
            $table->string('signup_source', 30)->nullable()->after('customer_tag');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('signup_source'));
        Schema::dropIfExists('visits');
    }
};
