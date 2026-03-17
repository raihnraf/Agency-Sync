<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    /**
     * Display list of client stores.
     */
    public function index(): View
    {
        return view("dashboard.tenants.index");
    }

    /**
     * Display form to create new client store.
     */
    public function create(): View
    {
        return view("dashboard.tenants.create");
    }

    /**
     * Display single client store details.
     */
    public function show(Request $request, string $id): View
    {
        return view("dashboard.tenants.show", ["tenantId" => $id]);
    }

    /**
     * Display form to edit client store.
     */
    public function edit(Request $request, string $id): View
    {
        return view("dashboard.tenants.edit", ["tenantId" => $id]);
    }

    /**
     * Display product search page for tenant.
     */
    public function products(Request $request, string $id): View
    {
        $tenant = $request->user()->tenants()->where("tenants.id", $id)->firstOrFail();

        return view("dashboard.tenants.products", [
            "tenantId" => $id,
            "tenantName" => $tenant->name
        ]);
    }

    /**
     * Return JSON list of tenants for AJAX calls (session authenticated).
     */
    public function indexJson(Request $request)
    {
        $tenants = $request->user()
            ->tenants()
            ->orderBy("name")
            ->get()
            ->map(function ($tenant) {
                return [
                    "id" => $tenant->id,
                    "name" => $tenant->name,
                    "platform_type" => $tenant->platform_type->value,
                    "platform_url" => $tenant->platform_url,
                    "status" => $tenant->status->value,
                    "created_at" => $tenant->created_at->toISOString(),
                ];
            });

        return response()->json([
            "data" => $tenants
        ]);
    }

    /**
     * Return JSON detail of single tenant for AJAX calls (session authenticated).
     */
    public function showJson(Request $request, string $id)
    {
        $tenant = $request->user()
            ->tenants()
            ->where("tenants.id", $id)
            ->firstOrFail();

        return response()->json([
            "data" => [
                "id" => $tenant->id,
                "name" => $tenant->name,
                "platform_type" => $tenant->platform_type->value,
                "platform_url" => $tenant->platform_url,
                "status" => $tenant->status->value,
                "created_at" => $tenant->created_at->toISOString(),
            ]
        ]);
    }

    /**
     * Update tenant via JSON endpoint (session authenticated).
     */
    public function updateJson(Request $request, string $id)
    {
        $tenant = $request->user()
            ->tenants()
            ->where("tenants.id", $id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,pending_setup,sync_error,suspended'],
            'platform_url' => ['sometimes', 'url', 'max:500'],
            'api_credentials' => ['sometimes', 'nullable', 'string'],
        ]);

        // Update basic fields
        if (isset($validated['name'])) {
            $tenant->name = $validated['name'];
        }
        if (isset($validated['status'])) {
            $tenant->status = $validated['status'];
        }
        if (isset($validated['platform_url'])) {
            $tenant->platform_url = $validated['platform_url'];
        }

        // Handle API credentials encryption if provided
        if (isset($validated['api_credentials']) && $validated['api_credentials'] !== '') {
            try {
                $credentials = json_decode($validated['api_credentials'], true, 512, JSON_THROW_ON_ERROR);
                // Encrypt and store credentials
                $tenant->api_credentials = encrypt($credentials);
            } catch (\JsonException $e) {
                return response()->json([
                    'errors' => [
                        [
                            'field' => 'api_credentials',
                            'message' => 'API credentials must be valid JSON'
                        ]
                    ]
                ], 422);
            }
        }

        $tenant->save();

        return response()->json([
            "data" => [
                "id" => $tenant->id,
                "name" => $tenant->name,
                "platform_type" => $tenant->platform_type->value,
                "platform_url" => $tenant->platform_url,
                "status" => $tenant->status->value,
                "created_at" => $tenant->created_at->toISOString(),
            ],
            "message" => "Client store updated successfully"
        ]);
    }

    /**
     * Store a newly created tenant in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'platform_type' => ['required', 'in:shopify,shopware'],
            'platform_url' => ['required', 'url', 'max:500'],
            'api_credentials' => ['required', 'string'],
        ]);

        try {
            $credentials = json_decode($validated['api_credentials'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return response()->json([
                'errors' => [
                    ['field' => 'api_credentials', 'message' => 'API credentials must be valid JSON']
                ]
            ], 422);
        }

        $tenant = $request->user()->tenants()->create([
            'name' => $validated['name'],
            'platform_type' => $validated['platform_type'],
            'platform_url' => $validated['platform_url'],
            'api_credentials' => encrypt($credentials),
            'status' => 'pending_setup',
        ]);

        return response()->json([
            'data' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'platform_type' => $tenant->platform_type->value,
                'platform_url' => $tenant->platform_url,
                'status' => $tenant->status->value,
            ],
            'message' => 'Client store created successfully'
        ], 201);
    }

}
