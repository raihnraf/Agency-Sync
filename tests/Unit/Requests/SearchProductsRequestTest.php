<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\SearchProductsRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Unit tests for SearchProductsRequest validation
 * 
 * @group search-validation
 */
class SearchProductsRequestTest extends TestCase
{
    protected SearchProductsRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new SearchProductsRequest();
    }

    public function test_query_is_required(): void
    {
        $validator = Validator::make(
            ['query' => ''],
            $this->request->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('query', $validator->errors()->toArray());
    }

    public function test_query_must_be_at_least_2_characters(): void
    {
        $validator = Validator::make(
            ['query' => 'a'],
            $this->request->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('query', $validator->errors()->toArray());
    }

    public function test_query_must_not_exceed_255_characters(): void
    {
        $validator = Validator::make(
            ['query' => str_repeat('a', 256)],
            $this->request->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('query', $validator->errors()->toArray());
    }

    public function test_valid_query_passes(): void
    {
        $validator = Validator::make(
            ['query' => 'iPhone 15'],
            $this->request->rules()
        );

        $this->assertFalse($validator->fails());
    }

    public function test_page_must_be_integer(): void
    {
        $validator = Validator::make(
            [
                'query' => 'test',
                'page' => 'not-a-number',
            ],
            $this->request->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('page', $validator->errors()->toArray());
    }

    public function test_page_must_be_at_least_1(): void
    {
        $validator = Validator::make(
            [
                'query' => 'test',
                'page' => 0,
            ],
            $this->request->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('page', $validator->errors()->toArray());
    }

    public function test_per_page_must_be_integer(): void
    {
        $validator = Validator::make(
            [
                'query' => 'test',
                'per_page' => 'not-a-number',
            ],
            $this->request->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('per_page', $validator->errors()->toArray());
    }

    public function test_per_page_must_be_at_least_1(): void
    {
        $validator = Validator::make(
            [
                'query' => 'test',
                'per_page' => 0,
            ],
            $this->request->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('per_page', $validator->errors()->toArray());
    }

    public function test_per_page_can_exceed_100_for_service_capping(): void
    {
        // Service will cap at 100, but validation allows higher values
        $validator = Validator::make(
            [
                'query' => 'test',
                'per_page' => 101,
            ],
            $this->request->rules()
        );

        // Validation should pass - capping happens in service
        $this->assertFalse($validator->fails());
    }

    public function test_valid_pagination_passes(): void
    {
        $validator = Validator::make(
            [
                'query' => 'test',
                'page' => 2,
                'per_page' => 50,
            ],
            $this->request->rules()
        );

        $this->assertFalse($validator->fails());
    }

    public function test_optional_parameters_have_defaults(): void
    {
        $validator = Validator::make(
            ['query' => 'test'],
            $this->request->rules()
        );

        $this->assertFalse($validator->fails());
    }
}
