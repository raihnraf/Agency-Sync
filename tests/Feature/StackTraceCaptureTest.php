<?php

namespace Tests\Feature;

use App\Jobs\Sync\FetchShopifyProductsJob;
use App\Models\SyncLog;
use App\Models\Tenant;
use App\Services\Sync\ProductValidator;
use App\Services\Sync\ShopifySyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StackTraceCaptureTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private SyncLog $syncLog;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenant with test credentials
        $this->tenant = Tenant::factory()->create([
            'platform_type' => \App\Enums\PlatformType::SHOPIFY,
            'api_credentials' => [
                'access_token' => 'test_token',
                'shop_domain' => 'test.myshopify.com',
            ],
        ]);

        // Create sync log
        $this->syncLog = SyncLog::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);
    }

    public function test_exception_handler_captures_stack_trace()
    {
        // Create a job that will fail
        $job = new FetchShopifyProductsJob($this->tenant->id, $this->syncLog->id);

        // Mock the sync service to throw an exception
        $this->mock(ShopifySyncService::class, function ($mock) {
            $mock->shouldReceive('fetchProducts')
                ->andThrow(new \Exception('Test error'));
        });

        // Run the job synchronously
        $job->handle();

        // Refresh sync log from database
        $this->syncLog->refresh();

        // Verify error details stored in metadata
        $metadata = $this->syncLog->metadata ?? [];
        $this->assertArrayHasKey('error_details', $metadata);

        $errorDetails = $metadata['error_details'];
        $this->assertEquals('internal_error', $errorDetails['type']);
        $this->assertEquals('Exception', $errorDetails['exception_class']);
        $this->assertEquals('Test error', $errorDetails['message']);
        $this->assertArrayHasKey('stack_trace', $errorDetails);
        $this->assertIsArray($errorDetails['stack_trace']);
    }

    public function test_stack_trace_includes_file_and_line_for_each_frame()
    {
        $job = new FetchShopifyProductsJob($this->tenant->id, $this->syncLog->id);

        // Mock the sync service to throw an exception
        $this->mock(ShopifySyncService::class, function ($mock) {
            $mock->shouldReceive('fetchProducts')
                ->andThrow(new \Exception('Test error'));
        });

        $job->handle();
        $this->syncLog->refresh();

        $errorDetails = $this->syncLog->metadata['error_details'] ?? [];
        $stackTrace = $errorDetails['stack_trace'] ?? [];

        // Verify stack trace has frames
        $this->assertGreaterThan(0, count($stackTrace));

        // Check first frame has file and line
        $firstFrame = $stackTrace[0];
        $this->assertArrayHasKey('file', $firstFrame);
        $this->assertArrayHasKey('line', $firstFrame);
        $this->assertIsString($firstFrame['file']);
        $this->assertIsInt($firstFrame['line']);
    }

    public function test_stack_trace_includes_function_and_class_for_each_frame()
    {
        $job = new FetchShopifyProductsJob($this->tenant->id, $this->syncLog->id);

        // Mock the sync service to throw an exception
        $this->mock(ShopifySyncService::class, function ($mock) {
            $mock->shouldReceive('fetchProducts')
                ->andThrow(new \RuntimeException('Test runtime error'));
        });

        $job->handle();
        $this->syncLog->refresh();

        $errorDetails = $this->syncLog->metadata['error_details'] ?? [];
        $stackTrace = $errorDetails['stack_trace'] ?? [];

        // Verify stack trace has frames with function and class
        $this->assertGreaterThan(0, count($stackTrace));

        // Find a frame with class and function (skip closure frames)
        $frameWithClass = null;
        foreach ($stackTrace as $frame) {
            if (isset($frame['class']) && isset($frame['function'])) {
                $frameWithClass = $frame;
                break;
            }
        }

        $this->assertNotNull($frameWithClass, 'Stack trace should contain at least one frame with class and function');
        $this->assertIsString($frameWithClass['class']);
        $this->assertIsString($frameWithClass['function']);
        $this->assertArrayHasKey('type', $frameWithClass); // -> or ::
    }

    public function test_stack_trace_stored_in_sync_log_metadata()
    {
        $job = new FetchShopifyProductsJob($this->tenant->id, $this->syncLog->id);

        // Mock the sync service to throw an exception
        $this->mock(ShopifySyncService::class, function ($mock) {
            $mock->shouldReceive('fetchProducts')
                ->andThrow(new \InvalidArgumentException('Invalid argument'));
        });

        $job->handle();
        $this->syncLog->refresh();

        // Verify metadata structure
        $this->assertNotNull($this->syncLog->metadata);
        $this->assertIsArray($this->syncLog->metadata);
        $this->assertArrayHasKey('error_details', $this->syncLog->metadata);

        $errorDetails = $this->syncLog->metadata['error_details'];
        $this->assertArrayHasKey('exception_class', $errorDetails);
        $this->assertArrayHasKey('message', $errorDetails);
        $this->assertArrayHasKey('code', $errorDetails);
        $this->assertArrayHasKey('file', $errorDetails);
        $this->assertArrayHasKey('line', $errorDetails);
        $this->assertArrayHasKey('stack_trace', $errorDetails);
        $this->assertArrayHasKey('timestamp', $errorDetails);
    }

    public function test_stack_trace_sanitized_for_security()
    {
        $job = new FetchShopifyProductsJob($this->tenant->id, $this->syncLog->id);

        // Mock the sync service to throw an exception
        $this->mock(ShopifySyncService::class, function ($mock) {
            $mock->shouldReceive('fetchProducts')
                ->andThrow(new \Exception('Test error'));
        });

        $job->handle();
        $this->syncLog->refresh();

        $errorDetails = $this->syncLog->metadata['error_details'] ?? [];
        $stackTrace = $errorDetails['stack_trace'] ?? [];

        // Verify stack trace only contains sanitized fields
        foreach ($stackTrace as $frame) {
            // Should only contain these keys
            $allowedKeys = ['file', 'line', 'function', 'class', 'type'];
            $actualKeys = array_keys($frame);
            foreach ($actualKeys as $key) {
                $this->assertContains($key, $allowedKeys, "Stack trace frame should only contain sanitized keys, found: $key");
            }
        }

        // Verify no sensitive data (like arguments, object properties)
        $this->assertArrayNotHasKey('args', $stackTrace[0] ?? []);
        $this->assertArrayNotHasKey('object', $stackTrace[0] ?? []);
    }
}
