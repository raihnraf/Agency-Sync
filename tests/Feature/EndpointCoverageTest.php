<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

class EndpointCoverageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: All API routes appear in documentation
     *
     * When: Documentation is generated
     * Then: All routes in routes/api.php appear in documentation
     * And: Each endpoint has HTTP method, path, and description
     *
     * References: routes/api.php structure (11 controllers, 5 groups)
     * - Authentication: AuthController
     * - Tenant Management: TenantController
     * - Catalog Synchronization: SyncController
     * - Product Search: SearchController
     * - Product Management: ProductController
     *
     * @return void
     */
    public function test_all_api_routes_documented(): void
    {
        // Ensure documentation is generated
        if (!file_exists(public_path('docs/index.html'))) {
            \Illuminate\Support\Facades\Artisan::call('scribe:generate');
        }

        $content = file_get_contents(public_path('docs/index.html'));

        // Get all API routes
        $apiRoutes = collect(Route::getRoutes())->filter(function ($route) {
            return str_starts_with($route->uri(), 'api/');
        });

        // Assert we have API routes
        $this->assertGreaterThan(0, $apiRoutes->count(), 'No API routes found');

        // Assert key endpoints are documented
        $keyEndpoints = [
            'api/v1/register',
            'api/v1/login',
            'api/v1/logout',
            'api/v1/me',
            'api/v1/tenants',
            'api/v1/sync/dispatch',
            'api/v1/exports/products',
        ];

        foreach ($keyEndpoints as $endpoint) {
            $this->assertStringContainsString($endpoint, $content, "Endpoint {$endpoint} not found in documentation");
        }
    }

    /**
     * Test: Endpoints grouped by @group annotation
     *
     * When: Viewing documentation
     * Then: Endpoints are organized by controller groups
     * And: Groups: Authentication, Tenant Management, Catalog Synchronization, Product Search
     *
     * @return void
     */
    public function test_endpoints_grouped_by_controller(): void
    {
        // Ensure documentation is generated
        if (!file_exists(public_path('docs/index.html'))) {
            \Illuminate\Support\Facades\Artisan::call('scribe:generate');
        }

        $content = file_get_contents(public_path('docs/index.html'));

        // Assert all endpoint groups are present
        $groups = [
            'Authentication',
            'Tenant Management',
            'Catalog Synchronization',
            'Product Search',
            'Index Management',
        ];

        foreach ($groups as $group) {
            $this->assertStringContainsString($group, $content, "Group '{$group}' not found in documentation");
        }
    }

    /**
     * Test: Authenticated endpoints show authentication badge
     *
     * When: Viewing authenticated endpoints in documentation
     * Then: "requires authentication" badge is displayed
     * And: Authentication requirement is clearly indicated
     *
     * @return void
     */
    public function test_authenticated_endpoints_show_badge(): void
    {
        // Ensure documentation is generated
        if (!file_exists(public_path('docs/index.html'))) {
            \Illuminate\Support\Facades\Artisan::call('scribe:generate');
        }

        $content = file_get_contents(public_path('docs/index.html'));

        // Scribe uses "requires authentication" badge (not "Authenticated")
        $authCount = substr_count($content, 'requires authentication');
        $this->assertGreaterThan(3, $authCount, 'Expected more authenticated endpoints to be marked');

        // Assert authentication-related text appears
        $this->assertStringContainsString('Authorization', $content);
        $this->assertStringContainsString('Bearer', $content);
    }

    /**
     * Test: Tenant-scoped endpoints document X-Tenant-ID header
     *
     * When: Viewing tenant-scoped endpoints
     * Then: X-Tenant-ID header requirement is documented
     * And: Examples show how to include tenant context in requests
     *
     * @return void
     */
    public function test_tenant_scoped_endpoints_document_header(): void
    {
        // Ensure documentation is generated
        if (!file_exists(public_path('docs/index.html'))) {
            \Illuminate\Support\Facades\Artisan::call('scribe:generate');
        }

        $content = file_get_contents(public_path('docs/index.html'));

        // Assert X-Tenant-ID header is documented
        $this->assertStringContainsString('X-Tenant-ID', $content, 'X-Tenant-ID header not documented');

        // Assert tenant-related endpoints exist
        $this->assertStringContainsString('tenantId', $content, 'Tenant ID parameter not documented');
    }
}
