<?php

namespace App\Events\Sync;

use App\Enums\PlatformType;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ProductsFetched implements ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public PlatformType $platformType;
    public Collection $products;
    public string $tenantId;

    public function __construct(PlatformType $platformType, Collection $products, string $tenantId)
    {
        $this->platformType = $platformType;
        $this->products = $products;
        $this->tenantId = $tenantId;
    }
}
