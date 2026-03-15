<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\SyncLog;
use App\Models\Tenant;
use App\Services\Sync\ProductValidator;
use App\Services\Sync\ShopifySyncService;
use Illuminate\Support\Facades\Http;

// Create tenant
$tenant = Tenant::factory()->create([
    'platform_type' => App\Enums\PlatformType::SHOPIFY,
    'api_credentials' => [
        'access_token' => 'test_token',
        'shop_domain' => 'test.myshopify.com',
    ],
]);

// Create sync log
$syncLog = SyncLog::factory()->create([
    'tenant_id' => $tenant->id,
    'status' => 'pending',
]);

// Mock failed response
Http::fake([
    'myshopify.com/admin/api/*/products.json' => Http::response(['errors' => 'Invalid API token'], 401),
]);

$validator = app(ProductValidator::class);
$syncService = new ShopifySyncService($validator, true);

try {
    $syncService->fetchProducts($tenant, $syncLog);
} catch (Exception $e) {
    $syncLog->refresh();
    echo 'Metadata: ' . json_encode($syncLog->metadata, JSON_PRETTY_PRINT);
    echo PHP_EOL;
    echo 'Error message: ' . $e->getMessage() . PHP_EOL;
}
