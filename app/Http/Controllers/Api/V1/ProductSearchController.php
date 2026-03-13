<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\SearchProductsRequest;
use App\Models\Tenant;
use App\Search\ProductSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Product Search Controller
 * 
 * Handles product search API endpoints with tenant isolation,
 * fuzzy matching, and pagination.
 */
class ProductSearchController extends ApiController
{
    protected ProductSearchService $searchService;

    public function __construct(ProductSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * Search products within a tenant's catalog
     * 
     * GET /api/v1/tenants/{tenantId}/search
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
     * Reindex all products for a tenant
     * 
     * POST /api/v1/tenants/{tenantId}/search/reindex
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
     * Get search index status for a tenant
     * 
     * GET /api/v1/tenants/{tenantId}/search/status
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
