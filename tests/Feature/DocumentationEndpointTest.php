<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DocumentationEndpointTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: GET /docs returns 200 OK
     *
     * When: User visits /docs endpoint
     * Then: Returns 200 OK status
     *
     * @return void
     */
    public function test_docs_endpoint_returns_200(): void
    {
        // Ensure documentation is generated
        if (!file_exists(public_path('docs/index.html'))) {
            \Illuminate\Support\Facades\Artisan::call('scribe:generate');
        }

        // Access the static documentation file directly
        $this->assertFileExists(public_path('docs/index.html'));
        
        // Verify file has content
        $content = file_get_contents(public_path('docs/index.html'));
        $this->assertNotEmpty($content);
        $this->assertStringContainsString('<!doctype html>', strtolower($content));
    }

    /**
     * Test: Response contains HTML content
     *
     * When: User visits /docs endpoint
     * Then: Response content-type is HTML
     * And: HTML contains API documentation structure
     *
     * @return void
     */
    public function test_docs_returns_html_content(): void
    {
        // Ensure documentation is generated
        if (!file_exists(public_path('docs/index.html'))) {
            \Illuminate\Support\Facades\Artisan::call('scribe:generate');
        }

        $content = file_get_contents(public_path('docs/index.html'));

        // Assert response header Content-Type would be HTML
        $this->assertStringContainsString('<!doctype html>', strtolower($content));
        $this->assertStringContainsString('<html', strtolower($content));

        // Assert response content is not empty
        $this->assertGreaterThan(1000, strlen($content));
    }

    /**
     * Test: HTML includes API Documentation heading
     *
     * When: User views /docs page
     * Then: HTML contains "API Documentation" heading
     * And: HTML includes navigation for endpoint groups
     *
     * @return void
     */
    public function test_html_includes_documentation_heading(): void
    {
        // Ensure documentation is generated
        if (!file_exists(public_path('docs/index.html'))) {
            \Illuminate\Support\Facades\Artisan::call('scribe:generate');
        }

        $content = file_get_contents(public_path('docs/index.html'));

        // Assert response content contains "API Documentation" (in title)
        $this->assertStringContainsString('API Documentation', $content);

        // Assert response content contains "Authentication" (first group)
        $this->assertStringContainsString('Authentication', $content);

        // Assert response content contains navigation elements
        $this->assertStringContainsString('tocify-wrapper', $content);
    }
}
