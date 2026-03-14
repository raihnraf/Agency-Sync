<?php

namespace App\Listeners;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class InvalidateProductCache
{
    /**
     * Handle the product event.
     */
    public function handle(Product $product): void
    {
        // Clear tenant-specific dashboard metrics (product count changes)
        Cache::forget("agency:dashboard:metrics:{$product->tenant_id}");
    }
}
