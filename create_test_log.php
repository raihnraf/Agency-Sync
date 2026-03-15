<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tenant;
use App\Models\SyncLog;
use Illuminate\Support\Str;

// Get or create a test tenant
$tenant = Tenant::first();
if (!$tenant) {
    $tenant = Tenant::create([
        'id' => (string) Str::uuid(),
        'user_id' => 1,
        'name' => 'Test Store',
        'slug' => 'test-store',
        'platform_type' => 'shopify',
        'platform_url' => 'https://test.myshopify.com',
        'shop_domain' => 'test.myshopify.com',
        'api_key' => 'test_key',
        'api_secret' => 'test_secret',
        'webhook_url' => 'https://test.com/webhook',
        'status' => 'active'
    ]);
    echo "Created test tenant: " . $tenant->name . "\n";
}

// Create a failed sync log with error details
$syncLog = SyncLog::create([
    'id' => (string) Str::uuid(),
    'tenant_id' => $tenant->id,
    'platform_type' => 'shopify',
    'status' => 'failed',
    'error_message' => 'API rate limit exceeded',
    'started_at' => now()->subMinutes(5),
    'completed_at' => now()->subMinute(2),
    'metadata' => [
        'error_details' => [
            'error_type' => 'api_rate_limit',
            'status' => 429,
            'body' => '{"error":"Rate limit exceeded"}',
            'headers' => [
                'X-Shopify-Shop-Api-Call-Limit' => '40/40'
            ],
            'timestamp' => now()->toIso8601String(),
            'request_url' => 'https://test.myshopify.com/admin/api/2024-01/products.json',
            'request_method' => 'GET'
        ],
        'products' => [
            'total' => 100,
            'processed' => 45,
            'failed' => 55,
            'indexed' => 40
        ]
    ]
]);

echo "Test failed sync log created: " . $syncLog->id . "\n";
echo "Tenant: " . $tenant->name . "\n";
echo "You can now view this error log at: http://localhost:8080/dashboard/error-log\n";
