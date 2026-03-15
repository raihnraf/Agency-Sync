<?php

namespace Tests\Feature;

use App\Jobs\Sync\FetchShopifyProductsJob;
use App\Models\SyncLog;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
        // Set tenant context
        \App\Models\Tenant::setCurrentTenant($this->tenant);

        // Mock HTTP to trigger an error (will be caught by job exception handler)
        Http::fake([
            'test.myshopify.com/admin/api/*/products.json*' => Http::response(['errors' => 'API error'], 500),
        ]);

        // Create and run the job (should throw exception after capturing stack trace)
        $job = new FetchShopifyProductsJob($this->tenant->id, $this->syncLog->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Shopify API error: 500');

        try {
            $job->handle();
        } catch (\Exception $e) {
            // Refresh sync log from database
            $this->syncLog->refresh();

            // Verify error details stored in metadata
            $metadata = $this->syncLog->metadata ?? [];
            $this->assertArrayHasKey('error_details', $metadata);

            $errorDetails = $metadata['error_details'];
            $this->assertEquals('internal_error', $errorDetails['type']);
            $this->assertEquals('Exception', $errorDetails['exception_class']);
            $this->assertArrayHasKey('stack_trace', $errorDetails);
            $this->assertIsArray($errorDetails['stack_trace']);

            throw $e;
        } finally {
            // Clear tenant context
            \App\Models\Tenant::setCurrentTenant(null);
        }
    }

    public function test_stack_trace_includes_file_and_line_for_each_frame()
    {
        \App\Models\Tenant::setCurrentTenant($this->tenant);

        // Mock HTTP to trigger an error
        Http::fake([
            'test.myshopify.com/admin/api/*/products.json*' => Http::response(['errors' => 'Not found'], 404),
        ]);

        $job = new FetchShopifyProductsJob($this->tenant->id, $this->syncLog->id);

        $this->expectException(\Exception::class);

        try {
            $job->handle();
        } catch (\Exception $e) {
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

            throw $e;
        } finally {
            \App\Models\Tenant::setCurrentTenant(null);
        }
    }

    public function test_stack_trace_includes_function_and_class_for_each_frame()
    {
        \App\Models\Tenant::setCurrentTenant($this->tenant);

        // Mock HTTP to trigger an error
        Http::fake([
            'test.myshopify.com/admin/api/*/products.json*' => Http::response(['errors' => 'Unauthorized'], 401),
        ]);

        $job = new FetchShopifyProductsJob($this->tenant->id, $this->syncLog->id);

        $this->expectException(\Exception::class);

        try {
            $job->handle();
        } catch (\Exception $e) {
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

            throw $e;
        } finally {
            \App\Models\Tenant::setCurrentTenant(null);
        }
    }

    public function test_stack_trace_stored_in_sync_log_metadata()
    {
        \App\Models\Tenant::setCurrentTenant($this->tenant);

        // Mock HTTP to trigger an error
        Http::fake([
            'test.myshopify.com/admin/api/*/products.json*' => Http::response(['errors' => 'Rate limit'], 429),
        ]);

        $job = new FetchShopifyProductsJob($this->tenant->id, $this->syncLog->id);

        $this->expectException(\Exception::class);

        try {
            $job->handle();
        } catch (\Exception $e) {
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

            throw $e;
        } finally {
            \App\Models\Tenant::setCurrentTenant(null);
        }
    }

    public function test_stack_trace_sanitized_for_security()
    {
        \App\Models\Tenant::setCurrentTenant($this->tenant);

        // Mock HTTP to trigger an error
        Http::fake([
            'test.myshopify.com/admin/api/*/products.json*' => Http::response(['errors' => 'Server error'], 503),
        ]);

        $job = new FetchShopifyProductsJob($this->tenant->id, $this->syncLog->id);

        $this->expectException(\Exception::class);

        try {
            $job->handle();
        } catch (\Exception $e) {
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

            throw $e;
        } finally {
            \App\Models\Tenant::setCurrentTenant(null);
        }
    }
}
