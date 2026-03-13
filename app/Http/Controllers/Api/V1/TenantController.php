<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\CreateTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Services\PlatformCredentialValidator;

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
     */
    public function index()
    {
        $tenants = auth()->user()->tenants()->paginate(20);

        return $this->success($tenants);
    }

    /**
     * Store a newly created tenant in storage.
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
     */
    public function show(string $id)
    {
        $tenant = auth()->user()->tenants()->where('tenants.id', $id)->firstOrFail();

        return $this->success(new TenantResource($tenant));
    }

    /**
     * Update the specified tenant in storage.
     */
    public function update(UpdateTenantRequest $request, string $id)
    {
        $tenant = auth()->user()->tenants()->where('tenants.id', $id)->firstOrFail();
        $tenant->update($request->validated());

        return $this->success(new TenantResource($tenant));
    }

    /**
     * Remove the specified tenant from storage.
     */
    public function destroy(string $id)
    {
        $tenant = auth()->user()->tenants()->where('tenants.id', $id)->firstOrFail();
        $tenant->delete();

        return $this->noContent();
    }
}
