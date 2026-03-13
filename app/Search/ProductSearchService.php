<?php

namespace App\Search;

use App\Models\Product;
use App\Models\Tenant;
use Elastic\Elasticsearch\Client;

/**
 * Product Search Service
 * 
 * Handles product search with fuzzy matching, pagination, and tenant isolation.
 * Provides index management operations for tenant-specific indices.
 */
class ProductSearchService
{
    protected IndexManager $indexManager;
    protected Client $client;

    public function __construct(IndexManager $indexManager, Client $client)
    {
        $this->indexManager = $indexManager;
        $this->client = $client;
    }

    /**
     * Search products within a tenant's catalog
     * 
     * @param Tenant $tenant The tenant to search within
     * @param string $query The search query
     * @param int $page Page number (1-based)
     * @param int $perPage Products per page (max 100)
     * @return array Search results with data and metadata
     */
    public function search(Tenant $tenant, string $query, int $page = 1, int $perPage = 20): array
    {
        // Ensure valid pagination
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));

        // Set tenant context for scoping
        Tenant::setCurrentTenant($tenant);

        // Build Scout search and use paginate for proper pagination
        $paginator = Product::search($query)->paginate($perPage, 'page', $page);
        
        // Get raw results for timing (using raw() on a new search with same query)
        $rawResults = Product::search($query)->raw();

        return [
            'data' => $paginator->items(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'query' => $query,
                'took' => $rawResults['took'] ?? 0,
            ],
        ];
    }

    /**
     * Create search index for a tenant
     */
    public function createTenantIndex(Tenant $tenant): void
    {
        $this->indexManager->createIndex($tenant);
    }

    /**
     * Delete search index for a tenant
     */
    public function deleteTenantIndex(Tenant $tenant): void
    {
        $this->indexManager->deleteIndex($tenant);
    }

    /**
     * Reindex all products for a tenant
     * 
     * Deletes existing index, recreates it, and indexes all tenant products
     */
    public function reindexTenantProducts(Tenant $tenant): void
    {
        // Delete existing index
        $this->deleteTenantIndex($tenant);

        // Create new index
        $this->createTenantIndex($tenant);

        // Set tenant context
        Tenant::setCurrentTenant($tenant);

        // Index all products in chunks
        Product::where('tenant_id', $tenant->id)
            ->chunk(100, function ($products) {
                $products->searchable();
            });
    }

    /**
     * Check if index exists for tenant
     */
    public function indexExists(Tenant $tenant): bool
    {
        return $this->indexManager->indexExists($tenant);
    }

    /**
     * Get index name for tenant
     */
    public function getIndexName(Tenant $tenant): string
    {
        return $this->indexManager->getIndexName($tenant);
    }
}
