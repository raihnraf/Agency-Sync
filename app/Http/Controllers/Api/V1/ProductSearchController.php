<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\SearchProductsRequest;
use App\Models\Tenant;
use App\Search\ProductSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Product Search
 *
 * API endpoints for searching products within tenant catalogs using Elasticsearch
 */
class ProductSearchController extends ApiController
{
    protected ProductSearchService $searchService;

    public function __construct(ProductSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * Search products within a tenant's catalog.
     *
     * Performs full-text search with fuzzy matching and pagination.
     *
     * @authenticated
     *
     * @urlParam tenantId string required Tenant UUID. Example: 123e4567-e89b-12d3-a456-426614174000
     * @queryParam query string required Search query. Example: blue shoes
     * @queryParam page integer Page number (default: 1). Example: 1
     * @queryParam per_page integer Results per page (default: 20, max: 100). Example: 20
     * @queryParam filters string optional JSON filters (stock_status, price_range). Example: {"stock_status":"in_stock"}
     *
     * @responseField data{0}.id string Product UUID
     * @responseField data{0}.name string Product name
     * @responseField data{0}.price number Product price
     * @responseField data{0}.stock_status string Stock status (in_stock, low_stock, out_of_stock)
     * @responseField meta.pagination.total integer Total results
     * @responseField meta.pagination.per_page integer Results per page
     * @responseField meta.pagination.current_page integer Current page
     *
     * @response {
     *   "data": [
     *     {
     *       "id": "uuid",
     *       "name": "Blue Running Shoes",
     *       "price": 89.99,
     *       "stock_status": "in_stock"
     *     }
     *   ],
     *   "meta": {
     *     "pagination": {
     *       "total": 150,
     *       "per_page": 20,
     *       "current_page": 1
     *     }
     *   }
     * }
     * @response 404 {
     *   "message": "Tenant not found or access denied"
     * }
     */
    public function search(SearchProductsRequest $request, string $tenantId): JsonResponse
    {
        // Verify tenant exists and belongs to authenticated user
        $tenant = Tenant::where('id', $tenantId)
            ->whereHas('users', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->first();

        if (!$tenant) {
            return $this->error('Tenant not found or access denied', 404);
        }

        // Execute search
        $results = $this->searchService->search(
            $tenant,
            $request->input('query'),
            (int) $request->input('page', 1),
            (int) $request->input('per_page', 20)
        );

        return $this->success($results);
    }

    /**
     * Reindex all products for a tenant.
     *
     * Rebuilds the Elasticsearch search index for all tenant products.
     *
     * @authenticated
     *
     * @urlParam tenantId string required Tenant UUID. Example: 123e4567-e89b-12d3-a456-426614174000
     *
     * @response 200 {
     *   "data": {
     *     "message": "Reindex completed successfully",
     *     "tenant_id": "uuid"
     *   }
     * }
     * @response 404 {
     *   "message": "Tenant not found or access denied"
     * }
     * @response 500 {
     *   "message": "Reindex failed: connection timeout"
     * }
     */
    public function reindex(Request $request, string $tenantId): JsonResponse
    {
        // Verify tenant exists and belongs to authenticated user
        $tenant = Tenant::where('id', $tenantId)
            ->whereHas('users', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->first();

        if (!$tenant) {
            return $this->error('Tenant not found or access denied', 404);
        }

        // Dispatch reindex as async job for large datasets
        // For now, we'll do it synchronously but wrapped in a try-catch
        try {
            $this->searchService->reindexTenantProducts($tenant);
            
            return $this->success([
                'message' => 'Reindex completed successfully',
                'tenant_id' => $tenant->id,
            ]);
        } catch (\Exception $e) {
            return $this->error('Reindex failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get search index status for a tenant.
     *
     * Returns information about the Elasticsearch index status.
     *
     * @authenticated
     *
     * @urlParam tenantId string required Tenant UUID. Example: 123e4567-e89b-12d3-a456-426614174000
     *
     * @responseField data.index_exists boolean Whether index exists
     * @responseField data.index_name string Elasticsearch index name
     * @responseField data.documents_count integer Number of indexed products
     * @responseField data.last_updated timestamp Last index update time
     *
     * @response {
     *   "data": {
     *     "index_exists": true,
     *     "index_name": "tenant_uuid_products",
     *     "tenant_id": "uuid"
     *   }
     * }
     * @response 404 {
     *   "message": "Tenant not found or access denied"
     * }
     */
    public function status(Request $request, string $tenantId): JsonResponse
    {
        // Verify tenant exists and belongs to authenticated user
        $tenant = Tenant::where('id', $tenantId)
            ->whereHas('users', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->first();

        if (!$tenant) {
            return $this->error('Tenant not found or access denied', 404);
        }

        $indexExists = $this->searchService->indexExists($tenant);
        $indexName = $this->searchService->getIndexName($tenant);

        return $this->success([
            'index_exists' => $indexExists,
            'index_name' => $indexName,
            'tenant_id' => $tenant->id,
        ]);
    }
}
