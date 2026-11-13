<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->decimal('balance', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 170)->unique();
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Input fields an admin defines inside a category (IMEI, SN, Email, Lock Code...).
        Schema::create('category_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_category_id')->constrained('service_categories')->cascadeOnDelete();
            $table->string('label', 100);
            $table->string('input_type', 30);
            $table->string('placeholder', 150)->nullable();
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_category_id')->constrained('service_categories')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('slug', 170)->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 14, 2);
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->string('delivery_time', 60)->nullable();
            // { "field_ids": [1,2], "execution": "manual" }
            $table->json('configuration')->nullable();
            $table->timestamps();
        });

        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->string('reference', 30)->unique();
            // Stops a double-clicked or re-sent form from charging twice.
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->decimal('amount', 14, 2);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending')->index();
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        // The ledger. Rows are only ever inserted, never edited or deleted.
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('transaction_type', ['recharge', 'deduction', 'refund', 'adjustment'])->index();
            // Signed: positive adds credit, negative removes it.
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_before', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->string('reference', 60)->index();
            $table->string('description')->nullable();
            $table->foreignId('service_request_id')->nullable()->constrained('service_requests')->nullOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60)->index();
            $table->string('description');
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('credit_transactions');
        Schema::dropIfExists('service_requests');
        Schema::dropIfExists('services');
        Schema::dropIfExists('category_fields');
        Schema::dropIfExists('service_categories');
        Schema::dropIfExists('customer_wallets');
    }
};
