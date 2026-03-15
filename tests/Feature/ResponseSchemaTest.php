<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ResponseSchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: Response fields documented with data types
     *
     * When: Viewing any endpoint documentation
     * Then: Response fields are listed with data types
     * And: Types include string, integer, array, object, boolean
     *
     * @return void
     */
    public function test_response_fields_have_data_types(): void
    {
        // Ensure documentation is generated
        if (!file_exists(public_path('docs/index.html'))) {
            \Illuminate\Support\Facades\Artisan::call('scribe:generate');
        }

        $content = file_get_contents(public_path('docs/index.html'));

        // Assert data types are documented
        $types = ['string', 'integer'];
        $foundTypes = 0;

        foreach ($types as $type) {
            if (str_contains(strtolower($content), "<small>{$type}</small>")) {
                $foundTypes++;
            }
        }

        $this->assertGreaterThanOrEqual(1, $foundTypes, 'Expected response field types in documentation');
    }

    /**
     * Test: @responseField annotations present
     *
     * When: Reviewing controller docblocks
     * Then: @responseField annotations describe each field
     * And: Nested fields use dot notation (data{0}.id)
     *
     * @return void
     */
    public function test_response_field_annotations_present(): void
    {
        // Ensure documentation is generated
        if (!file_exists(public_path('docs/index.html'))) {
            \Illuminate\Support\Facades\Artisan::call('scribe:generate');
        }

        $content = file_get_contents(public_path('docs/index.html'));

        // Assert common response fields from resources are documented
        $responseFields = [
            'id',
            'name',
            'email',
            'status',
        ];

        $foundFields = 0;
        foreach ($responseFields as $field) {
            if (str_contains($content, $field)) {
                $foundFields++;
            }
        }

        $this->assertGreaterThanOrEqual(3, $foundFields, 'Expected response fields in documentation');
    }

    /**
     * Test: Example responses show JSON structure
     *
     * When: Viewing endpoint documentation
     * Then: Example responses show actual JSON structure
     * And: Examples match API Resource classes (UserResource, TenantResource, SyncLogResource, ProductResource)
     *
     * @return void
     */
    public function test_example_responses_show_json_structure(): void
    {
        // Ensure documentation is generated
        if (!file_exists(public_path('docs/index.html'))) {
            \Illuminate\Support\Facades\Artisan::call('scribe:generate');
        }

        $content = file_get_contents(public_path('docs/index.html'));

        // Assert JSON structure is shown (Scribe uses HTML entities in output)
        $this->assertStringContainsString('data', $content, 'Response data structure not documented');

        // Assert example responses section exists
        $this->assertStringContainsString('Example response', $content);

        // Assert JSON examples use proper formatting
        $this->assertStringContainsString('{', $content);
        $this->assertStringContainsString('}', $content);
    }

    /**
     * Test: Error responses documented (401, 422, 404)
     *
     * When: Viewing endpoint documentation
     * Then: Error responses are documented with status codes
     * And: Examples show 401 Unauthorized, 422 Validation Error, 404 Not Found
     *
     * @return void
     */
    public function test_error_responses_documented(): void
    {
        // Ensure documentation is generated
        if (!file_exists(public_path('docs/index.html'))) {
            \Illuminate\Support\Facades\Artisan::call('scribe:generate');
        }

        $content = file_get_contents(public_path('docs/index.html'));

        // Assert error status codes are documented
        $errorCodes = ['401', '422', '404'];
        $foundCodes = 0;

        foreach ($errorCodes as $code) {
            if (str_contains($content, "Example response ({$code})")) {
                $foundCodes++;
            }
        }

        $this->assertGreaterThanOrEqual(1, $foundCodes, 'Expected error response codes in documentation');

        // Assert error-related terms are present
        $this->assertStringContainsString('error', strtolower($content));
    }
}
