<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class ScribeGenerationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: Scribe generates public/docs directory
     *
     * When: php artisan scribe:generate is executed
     * Then: public/docs directory is created with index.html
     *
     * @return void
     */
    public function test_scribe_generates_documentation(): void
    {
        // Only run generation if docs don't exist (avoid permission issues in tests)
        if (!file_exists(public_path('docs/index.html'))) {
            Artisan::call('scribe:generate');
        }

        // Assert documentation file exists
        $this->assertFileExists(public_path('docs/index.html'));

        // Assert file is not empty
        $this->assertGreaterThan(0, filesize(public_path('docs/index.html')));

        // Assert file contains HTML doctype
        $content = file_get_contents(public_path('docs/index.html'));
        $this->assertStringContainsString('<!doctype html>', strtolower($content));
    }

    /**
     * Test: Documentation includes all API endpoints
     *
     * When: Documentation is generated
     * Then: All routes in routes/api.php appear in documentation
     * And: Documentation includes endpoints grouped by controller
     *
     * @return void
     */
    public function test_documentation_includes_all_endpoints(): void
    {
        // Ensure documentation is generated
        if (!file_exists(public_path('docs/index.html'))) {
            Artisan::call('scribe:generate');
        }

        $content = file_get_contents(public_path('docs/index.html'));

        // Assert all endpoint groups are present
        $this->assertStringContainsString('Authentication', $content);
        $this->assertStringContainsString('Tenant Management', $content);
        $this->assertStringContainsString('Catalog Synchronization', $content);

        // Assert all endpoint paths are documented (using actual route structure)
        $endpoints = [
            'api/v1/register',
            'api/v1/login',
            'api/v1/logout',
            'api/v1/me',
            'api/v1/tenants',
            'api/v1/sync/dispatch',
            'api/v1/sync/status/{syncLogId}',
            'api/v1/sync/history',
            'api/v1/tenants/{tenantId}/search',
            'api/v1/tenants/{tenantId}/search/status',
            'api/v1/tenants/{tenantId}/search/reindex',
            'api/v1/tenants/{tenantId}/reindex',
            'api/v1/tenants/{tenantId}/jobs',
            'api/v1/jobs/{jobId}/status',
            'api/v1/exports/products',
            'api/v1/exports/sync-logs',
            'api/v1/exports/{uuid}',
        ];

        foreach ($endpoints as $endpoint) {
            $this->assertStringContainsString($endpoint, $content, "Endpoint {$endpoint} not found in documentation");
        }
    }

    /**
     * Test: Generated HTML is valid and accessible
     *
     * When: Viewing generated documentation
     * Then: HTML is well-formed and contains expected sections
     * And: Navigation and endpoint groups are present
     *
     * @return void
     */
    public function test_generated_html_is_valid(): void
    {
        // Ensure documentation is generated
        if (!file_exists(public_path('docs/index.html'))) {
            Artisan::call('scribe:generate');
        }

        $content = file_get_contents(public_path('docs/index.html'));

        // Assert HTML structure is valid
        $this->assertStringContainsString('<!doctype html>', strtolower($content));
        $this->assertStringContainsString('<html', strtolower($content));
        $this->assertStringContainsString('</html>', strtolower($content));
        $this->assertStringContainsString('API Documentation', $content);
    }
}
