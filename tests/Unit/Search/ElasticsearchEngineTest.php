<?php

namespace Tests\Unit\Search;

use App\Engines\ElasticsearchEngine;
use App\Models\Product;
use App\Search\IndexManager;
use Elastic\Elasticsearch\Client;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Scout\Builder;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

/**
 * Unit tests for ElasticsearchEngine
 * 
 * Tests SEARCH-05, SEARCH-07 requirements:
 * - SEARCH-05: Products searchable via Scout's searchable()/unsearchable()
 * - SEARCH-07: Custom Scout engine for Elasticsearch 8.x
 * 
 * @group elasticsearch-engine
 */
class ElasticsearchEngineTest extends TestCase
{
    protected function createEngine(): ElasticsearchEngine
    {
        // Use reflection to create engine without calling constructor
        // This avoids the need to mock the final Client class
        $reflector = new ReflectionClass(ElasticsearchEngine::class);
        $engine = $reflector->newInstanceWithoutConstructor();
        
        return $engine;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_build_query_creates_multi_match_with_fuzziness(): void
    {
        $engine = $this->createEngine();

        // Use reflection to test protected method
        $method = new \ReflectionMethod($engine, 'buildQuery');
        $method->setAccessible(true);

        $builder = Mockery::mock(Builder::class);
        $builder->query = 'iphone';

        $query = $method->invoke($engine, $builder);

        $this->assertArrayHasKey('bool', $query);
        $this->assertArrayHasKey('must', $query['bool']);
        $this->assertArrayHasKey('multi_match', $query['bool']['must']);
        
        $multiMatch = $query['bool']['must']['multi_match'];
        $this->assertEquals('iphone', $multiMatch['query']);
        $this->assertEquals('AUTO', $multiMatch['fuzziness']);
        $this->assertContains('name^3', $multiMatch['fields']);
        $this->assertContains('description^2', $multiMatch['fields']);
        $this->assertContains('sku', $multiMatch['fields']);
    }

    public function test_build_query_uses_prefix_length_3(): void
    {
        $engine = $this->createEngine();

        $method = new \ReflectionMethod($engine, 'buildQuery');
        $method->setAccessible(true);

        $builder = Mockery::mock(Builder::class);
        $builder->query = 'test';

        $query = $method->invoke($engine, $builder);
        
        $multiMatch = $query['bool']['must']['multi_match'];
        $this->assertEquals(3, $multiMatch['prefix_length']);
    }

    public function test_map_returns_collection_ordered_by_relevance(): void
    {
        $engine = $this->createEngine();

        $results = [
            'hits' => [
                'hits' => [
                    ['_id' => 'product-1'],
                    ['_id' => 'product-2'],
                    ['_id' => 'product-3'],
                ],
            ],
        ];

        $model = Mockery::mock(Product::class);
        $model->shouldReceive('getScoutModelsByIds')->andReturn(new Collection([
            'product-1' => (object)['id' => 'product-1'],
            'product-2' => (object)['id' => 'product-2'],
            'product-3' => (object)['id' => 'product-3'],
        ]));
        $model->shouldReceive('getScoutKeyName')->andReturn('id');
        $model->shouldReceive('newCollection')->andReturnUsing(function ($items = []) {
            return new Collection($items);
        });

        $builder = Mockery::mock(Builder::class);

        $mapped = $engine->map($builder, $results, $model);

        $this->assertInstanceOf(Collection::class, $mapped);
        $this->assertCount(3, $mapped);
        $this->assertEquals('product-1', $mapped->first()->id);
    }

    public function test_map_ids_returns_collection_of_ids(): void
    {
        $engine = $this->createEngine();

        $results = [
            'hits' => [
                'hits' => [
                    ['_id' => 'product-1'],
                    ['_id' => 'product-2'],
                    ['_id' => 'product-3'],
                ],
            ],
        ];

        $ids = $engine->mapIds($results);

        $this->assertCount(3, $ids);
        $this->assertEquals('product-1', $ids->first());
        $this->assertEquals(['product-1', 'product-2', 'product-3'], $ids->values()->all());
    }

    public function test_map_ids_returns_empty_collection_for_empty_results(): void
    {
        $engine = $this->createEngine();

        $ids = $engine->mapIds(['hits' => ['hits' => []]]);

        $this->assertCount(0, $ids);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $ids);
    }

    public function test_get_total_count_returns_total_hits(): void
    {
        $engine = $this->createEngine();

        $results = [
            'hits' => [
                'total' => [
                    'value' => 42,
                ],
            ],
        ];

        $count = $engine->getTotalCount($results);

        $this->assertEquals(42, $count);
    }

    public function test_get_total_count_returns_zero_for_empty_results(): void
    {
        $engine = $this->createEngine();

        $count = $engine->getTotalCount(['hits' => []]);

        $this->assertEquals(0, $count);
    }

    public function test_map_returns_empty_collection_for_empty_results(): void
    {
        $engine = $this->createEngine();

        $model = Mockery::mock(Product::class);
        $model->shouldReceive('newCollection')->andReturnUsing(function ($items = []) {
            return new Collection($items);
        });

        $builder = Mockery::mock(Builder::class);

        $mapped = $engine->map($builder, ['hits' => ['hits' => []]], $model);

        $this->assertInstanceOf(Collection::class, $mapped);
        $this->assertCount(0, $mapped);
    }
}
