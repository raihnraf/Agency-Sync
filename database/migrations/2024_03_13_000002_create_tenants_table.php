<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('platform_type', ['shopify', 'shopware']);
            $table->string('platform_url');
            $table->enum('status', ['active', 'pending_setup', 'sync_error', 'suspended'])->default('pending_setup');
            $table->json('api_credentials'); // Encrypted at app layer via cast
            $table->json('settings')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->string('sync_status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
