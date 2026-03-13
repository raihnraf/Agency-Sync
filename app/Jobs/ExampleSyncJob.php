<?php

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

class ExampleSyncJob extends TenantAwareJob
{
    public function __construct(string $tenantId, public array $data = [])
    {
        parent::__construct($tenantId);
    }

    public function handle(): void
    {
        $tenant = Tenant::currentTenant();

        Log::info('Example sync job executing', [
            'tenant_id' => $this->tenantId,
            'tenant_name' => $tenant->name ?? 'unknown',
            'data' => $this->data,
        ]);

        // Simulate work (will be replaced with real sync logic in Phase 6)
        sleep(2);

        Log::info('Example sync job completed', [
            'tenant_id' => $this->tenantId,
        ]);
    }
}
