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
        Schema::table('products', function (Blueprint $table) {
            // Add external_id column if it doesn't exist
            if (!Schema::hasColumn('products', 'external_id')) {
                $table->string('external_id', 255)->nullable()->after('tenant_id');
            }

            // Add platform column if it doesn't exist
            if (!Schema::hasColumn('products', 'platform')) {
                $table->enum('platform', ['shopify', 'shopware'])->nullable()->after('stock_quantity');
            }

            // Add unique constraint on (tenant_id, external_id) if external_id exists
            if (Schema::hasColumn('products', 'external_id')) {
                // Drop the old unique index if it exists on external_id alone
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                $indexes = $sm->listTableIndexes('products');

                if (isset($indexes['products_external_id_unique'])) {
                    $table->dropUnique(['external_id']);
                }

                // Add composite unique constraint
                $table->unique(['tenant_id', 'external_id'], 'unique_tenant_external_product');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'platform')) {
                $table->dropColumn(['platform']);
            }

            if (Schema::hasColumn('products', 'external_id')) {
                $table->dropUnique(['tenant_id', 'external_id']);
                $table->dropColumn(['external_id']);
            }
        });
    }
};
