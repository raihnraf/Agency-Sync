<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Queue\Middleware\SetTenantContext;

abstract class TenantAwareJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $tenantId;
    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(string $tenantId)
    {
        $this->tenantId = $tenantId;
        $this->queue = 'sync';
    }

    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function middleware(): array
    {
        return [new SetTenantContext];
    }

    abstract public function handle(): void;
}
