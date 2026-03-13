<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\UpdateTenantRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateTenantRequestTest extends TestCase
{
    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = new UpdateTenantRequest();

        return Validator::make($data, $request->rules());
    }

    public function test_all_fields_are_optional()
    {
        $validator = $this->validate([]);

        $this->assertFalse($validator->fails());
    }

    public function test_name_must_be_string_when_provided()
    {
        $validator = $this->validate(['name' => 123]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_name_max_255_when_provided()
    {
        $validator = $this->validate(['name' => str_repeat('a', 256)]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_status_must_be_in_allowed_values_when_provided()
    {
        $validator = $this->validate(['status' => 'invalid']);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('status', $validator->errors()->toArray());
    }

    public function test_status_accepts_active()
    {
        $validator = $this->validate(['status' => 'active']);

        $this->assertFalse($validator->fails());
    }

    public function test_status_accepts_pending_setup()
    {
        $validator = $this->validate(['status' => 'pending_setup']);

        $this->assertFalse($validator->fails());
    }

    public function test_status_accepts_sync_error()
    {
        $validator = $this->validate(['status' => 'sync_error']);

        $this->assertFalse($validator->fails());
    }

    public function test_status_accepts_suspended()
    {
        $validator = $this->validate(['status' => 'suspended']);

        $this->assertFalse($validator->fails());
    }

    public function test_platform_url_must_be_valid_url_when_provided()
    {
        $validator = $this->validate(['platform_url' => 'not-a-url']);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('platform_url', $validator->errors()->toArray());
    }

    public function test_platform_url_max_500_when_provided()
    {
        $validator = $this->validate([
            'platform_url' => 'https://example.com/' . str_repeat('a', 500)
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('platform_url', $validator->errors()->toArray());
    }

    public function test_settings_must_be_array_when_provided()
    {
        $validator = $this->validate(['settings' => 'not-an-array']);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('settings', $validator->errors()->toArray());
    }

    public function test_valid_update_data_passes_validation()
    {
        $validator = $this->validate([
            'name' => 'Updated Tenant Name',
            'status' => 'active',
            'platform_url' => 'https://example.myshopify.com',
            'settings' => ['webhook_enabled' => true],
        ]);

        $this->assertFalse($validator->fails());
    }
}
