<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\CreateTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Services\PlatformCredentialValidator;
use Illuminate\Support\Facades\Cache;

/**
 * @group Tenant Management
 *
 * API endpoints for managing client stores (tenants)
 */
class TenantController extends ApiController
{
    private PlatformCredentialValidator $credentialValidator;

    /**
     * Create a new controller instance.
     */
    public function __construct(PlatformCredentialValidator $credentialValidator)
    {
        $this->credentialValidator = $credentialValidator;
    }

    /**
     * Display a listing of the user's tenants.
     *
     * Returns all tenants associated with the authenticated user.
     *
     * @authenticated
     *
     * @responseField data{0}.id string Tenant UUID
     * @responseField data{0}.name string Tenant name
     * @responseField data{0}.slug string URL-friendly identifier
     * @responseField data{0}.status string Tenant status (active, pending, error)
     * @responseField data{0}.platform_type string Platform (shopify, shopware)
     * @responseField data{0}.platform_url string Platform base URL
     *
     * @response {
     *   "data": [
     *     {
     *       "id": "uuid",
     *       "name": "My Shopify Store",
     *       "slug": "my-shopify-store",
     *       "status": "active",
     *       "platform_type": "shopify",
     *       "platform_url": "https://store.myshopify.com"
     *     }
     *   ]
     * }
     */
    public function index()
    {
        $userId = auth()->id();
        
        // Cache tenant list for 15 minutes per user
        $tenants = Cache::remember("agency:tenants:list:{$userId}", 900, function () {
            return auth()->user()->tenants()
                ->select('tenants.id', 'tenants.name', 'tenants.slug', 'tenants.status', 'tenants.platform_type', 'tenants.platform_url')
                ->orderBy('tenants.name')
                ->get();
        });

        return $this->success($tenants);
    }

    /**
     * Store a newly created tenant in storage.
     *
     * Creates a new tenant with encrypted API credentials.
     *
     * @authenticated
     *
     * @bodyParam name string required Tenant name. Example: My Client Store
     * @bodyParam platform_type string required Platform (shopify, shopware). Example: shopify
     * @bodyParam platform_url string required Platform base URL. Example: https://store.myshopify.com
     * @bodyParam api_credentials object required API credentials object
     * @bodyParam api_credentials.api_key string required API key. Example: api_key_123
     * @bodyParam api_credentials.api_secret string required API secret. Example: secret_456
     * @bodyParam settings object optional Additional settings
     *
     * @response 201 {
     *   "data": {
     *     "id": "uuid",
     *     "name": "My Client Store",
     *     "slug": "my-client-store",
     *     "status": "active",
     *     "platform_type": "shopify",
     *     "platform_url": "https://store.myshopify.com"
     *   },
     *   "meta": {
     *     "message": "Tenant created successfully"
     *   }
     * }
     * @response 422 {
     *   "errors": [
     *     {
     *       "field": "api_credentials",
     *       "message": "Invalid API credentials"
     *     }
     *   ]
     * }
     */
    public function store(CreateTenantRequest $request)
    {
        // Validate credentials with platform API
        $isValid = $this->credentialValidator->validate(
            $request->platform_type,
            $request->api_credentials,
            $request->platform_url
        );

        if (!$isValid) {
            return $this->error([
                [
                    'field' => 'api_credentials',
                    'message' => 'Invalid API credentials',
                    'platform_response' => $this->credentialValidator->getLastError(),
                ]
            ], 422);
        }

        // Create tenant
        $tenant = Tenant::create($request->validated());

        // Attach user as admin
        auth()->user()->tenants()->attach($tenant->id, [
            'role' => 'admin',
            'joined_at' => now(),
        ]);

        return $this->created(new TenantResource($tenant), [
            'message' => 'Tenant created successfully'
        ]);
    }

    /**
     * Display the specified tenant.
     *
     * Returns detailed information about a specific tenant.
     *
     * @authenticated
     *
     * @urlParam id string required Tenant UUID
     * @header X-Tenant-ID required for tenant-scoped requests
     *
     * @responseField data.id string Tenant UUID
     * @responseField data.name string Tenant name
     * @responseField data.slug string URL-friendly identifier
     * @responseField data.status string Tenant status
     * @responseField data.platform_type string Platform type
     * @responseField data.platform_url string Platform base URL
     *
     * @response {
     *   "data": {
     *     "id": "uuid",
     *     "name": "My Client Store",
     *     "slug": "my-client-store",
     *     "status": "active",
     *     "platform_type": "shopify",
     *     "platform_url": "https://store.myshopify.com"
     *   }
     * }
     * @response 404 {
     *   "message": "Tenant not found"
     * }
     */
    public function show(string $id)
    {
        $tenant = auth()->user()->tenants()->where('tenants.id', $id)->firstOrFail();

        return $this->success(new TenantResource($tenant));
    }

    /**
     * Update the specified tenant in storage.
     *
     * Updates tenant information. Only provided fields will be updated.
     *
     * @authenticated
     *
     * @urlParam id string required Tenant UUID
     * @header X-Tenant-ID required
     *
     * @bodyParam name string optional Updated tenant name. Example: Updated Store Name
     * @bodyParam status string optional Updated status (active, pending, error). Example: active
     *
     * @response 200 {
     *   "data": {
     *     "id": "uuid",
     *     "name": "Updated Store Name",
     *     "slug": "my-client-store",
     *     "status": "active",
     *     "platform_type": "shopify",
     *     "platform_url": "https://store.myshopify.com"
     *   }
     * }
     * @response 422 {
     *   "errors": [
     *     {
     *       "field": "status",
     *       "message": "The selected status is invalid."
     *     }
     *   ]
     * }
     * @response 404 {
     *   "message": "Tenant not found"
     * }
     */
    public function update(UpdateTenantRequest $request, string $id)
    {
        $tenant = auth()->user()->tenants()->where('tenants.id', $id)->firstOrFail();
        $tenant->update($request->validated());

        return $this->success(new TenantResource($tenant));
    }

    /**
     * Remove the specified tenant from storage.
     *
     * Permanently deletes the tenant and all associated data.
     *
     * @authenticated
     *
     * @urlParam id string required Tenant UUID
     * @header X-Tenant-ID required
     *
     * @response 204
     * @response 404 {
     *   "message": "Tenant not found"
     * }
     */
    public function destroy(string $id)
    {
        $tenant = auth()->user()->tenants()->where('tenants.id', $id)->firstOrFail();
        $tenant->delete();

        return $this->noContent();
    }
}
