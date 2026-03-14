<?php

namespace App\Listeners;

use App\Models\SyncLog;
use Illuminate\Support\Facades\Cache;

class InvalidateSyncLogCache
{
    /**
     * Handle the sync log event.
     */
    public function handle(SyncLog $syncLog): void
    {
        // Clear tenant-specific dashboard metrics (last sync status changes)
        Cache::forget("agency:dashboard:metrics:{$syncLog->tenant_id}");
    }
}
