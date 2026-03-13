<?php

namespace Tests\Unit\Search;

use App\Models\Tenant;
use App\Search\IndexManager;
use App\Search\ProductSearchService;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

/**
 * Unit tests for ProductSearchService
 * 
 * Tests search functionality, pagination, and index management.
 * 
 * @group product-search-service
 */
class ProductSearchServiceTest extends TestCase
{
    protected function createService(): array
    {
        // Use reflection to create service without calling constructor
        $reflector = new ReflectionClass(ProductSearchService::class);
        $service = $reflector->newInstanceWithoutConstructor();
        
        // Mock IndexManager
        $indexManager = Mockery::mock(IndexManager::class);
        
        // Inject via reflection
        $property = $reflector->getProperty('indexManager');
        $property->setAccessible(true);
        $property->setValue($service, $indexManager);
        
        return [$service, $indexManager];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_service_can_be_instantiated_via_reflection(): void
    {
        [$service] = $this->createService();

        $this->assertInstanceOf(ProductSearchService::class, $service);
    }

    public function test_create_tenant_index_delegates_to_index_manager(): void
    {
        [$service, $indexManager] = $this->createService();

        $tenant = new Tenant();
        $tenant->id = 'tenant-123';

        $indexManager->shouldReceive('createIndex')
            ->once()
            ->with($tenant);

        $service->createTenantIndex($tenant);
    }

    public function test_delete_tenant_index_delegates_to_index_manager(): void
    {
        [$service, $indexManager] = $this->createService();

        $tenant = new Tenant();
        $tenant->id = 'tenant-123';

        $indexManager->shouldReceive('deleteIndex')
            ->once()
            ->with($tenant);

        $service->deleteTenantIndex($tenant);
    }

    public function test_index_exists_delegates_to_index_manager(): void
    {
        [$service, $indexManager] = $this->createService();

        $tenant = new Tenant();
        $tenant->id = 'tenant-123';

        $indexManager->shouldReceive('indexExists')
            ->once()
            ->with($tenant)
            ->andReturn(true);

        $result = $service->indexExists($tenant);

        $this->assertTrue($result);
    }

    public function test_get_index_name_delegates_to_index_manager(): void
    {
        [$service, $indexManager] = $this->createService();

        $tenant = new Tenant();
        $tenant->id = 'tenant-123';

        $indexManager->shouldReceive('getIndexName')
            ->once()
            ->with($tenant)
            ->andReturn('products_tenant_tenant-123');

        $result = $service->getIndexName($tenant);

        $this->assertEquals('products_tenant_tenant-123', $result);
    }
}
