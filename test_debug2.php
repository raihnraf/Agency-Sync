<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\SyncLog;
use App\Models\Tenant;
use App\Services\Sync\ProductValidator;
use App\Services\Sync\ShopifySyncService;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;

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

echo "Before fake" . PHP_EOL;

// Mock failed response - use withoutExceptionHandling to prevent auto-throw
Http::fake(function ($request) {
    echo "Fake called" . PHP_EOL;
    return Http::response(['errors' => 'Invalid API token'], 401);
});

echo "After fake" . PHP_EOL;

$validator = app(ProductValidator::class);
$syncService = new ShopifySyncService($validator, true);

echo "Before fetchProducts" . PHP_EOL;

try {
    $syncService->fetchProducts($tenant, $syncLog);
    echo "After fetchProducts - no exception" . PHP_EOL;
} catch (\Exception $e) {
    echo "Exception caught: " . get_class($e) . PHP_EOL;
    echo "Message: " . $e->getMessage() . PHP_EOL;
    echo "File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;

    $syncLog->refresh();
    echo "Metadata: " . json_encode($syncLog->metadata, JSON_PRETTY_PRINT) . PHP_EOL;
}
