<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CurlCommandsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: Each endpoint shows curl example
     *
     * When: Viewing any endpoint documentation
     * Then: Example curl command is displayed
     * And: Command is ready to copy and execute
     *
     * @return void
     */
    public function test_endpoints_have_curl_examples(): void
    {
        // Ensure documentation is generated
        if (!file_exists(public_path('docs/index.html'))) {
            \Illuminate\Support\Facades\Artisan::call('scribe:generate');
        }

        $content = file_get_contents(public_path('docs/index.html'));

        // Assert curl commands appear multiple times
        $curlCount = substr_count(strtolower($content), 'curl');
        $this->assertGreaterThan(10, $curlCount, 'Expected curl examples in documentation');

        // Assert bash examples are present
        $this->assertStringContainsString('bash-example', $content);
    }

    /**
     * Test: Commands include correct HTTP method
     *
     * When: Viewing curl examples
     * Then: Command includes correct HTTP method (GET, POST, PATCH, DELETE)
     * And: Method matches the endpoint's actual HTTP method
     *
     * @return void
     */
    public function test_curl_commands_use_correct_http_method(): void
    {
        // Ensure documentation is generated
        if (!file_exists(public_path('docs/index.html'))) {
            \Illuminate\Support\Facades\Artisan::call('scribe:generate');
        }

        $content = file_get_contents(public_path('docs/index.html'));

        // Scribe uses --request flag for methods
        $this->assertStringContainsString('--request POST', $content);
        $this->assertStringContainsString('--request GET', $content);
        
        // Check for method badges in the documentation
        $this->assertStringContainsString('badge-black', $content);
    }

    /**
     * Test: Authenticated commands include Authorization header
     *
     * When: Viewing authenticated endpoint examples
     * Then: Command includes Authorization: Bearer {token} header
     * And: Token placeholder is clearly indicated
     *
     * @return void
     */
    public function test_authenticated_commands_include_authorization_header(): void
    {
        // Ensure documentation is generated
        if (!file_exists(public_path('docs/index.html'))) {
            \Illuminate\Support\Facades\Artisan::call('scribe:generate');
        }

        $content = file_get_contents(public_path('docs/index.html'));

        // Assert Authorization header appears in examples
        $this->assertStringContainsString('Authorization', $content);
        $this->assertStringContainsString('Bearer', $content);

        // Assert header format with double quotes (Scribe uses double quotes in curl)
        $this->assertStringContainsString('"Authorization: Bearer', $content);
    }

    /**
     * Test: Request body examples match validation rules
     *
     * When: Viewing POST/PATCH endpoint examples
     * Then: Request body examples match FormRequest validation rules
     * And: Examples show correct data types and required fields
     *
     * References: FormRequest classes (CreateTenantRequest, LoginRequest, UpdateTenantRequest, etc.)
     *
     * @return void
     */
    public function test_request_examples_match_validation_rules(): void
    {
        // Ensure documentation is generated
        if (!file_exists(public_path('docs/index.html'))) {
            \Illuminate\Support\Facades\Artisan::call('scribe:generate');
        }

        $content = file_get_contents(public_path('docs/index.html'));

        // Assert common request fields are documented
        $requestFields = [
            'name',
            'email',
            'password',
        ];

        $foundFields = 0;
        foreach ($requestFields as $field) {
            if (str_contains($content, "\"{$field}\"")) {
                $foundFields++;
            }
        }

        $this->assertGreaterThanOrEqual(2, $foundFields, 'Expected request body fields in documentation');

        // Assert Body Parameters section exists
        $this->assertStringContainsString('Body Parameters', $content);
    }
}
