<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SyncLog
 */
class SyncLogResource extends JsonResource
{
    /**
     * Disable resource wrapping.
     */
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'platform_type' => $this->platform_type->value,
            'status' => $this->status->value,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'total_products' => $this->total_products,
            'processed_products' => $this->processed_products,
            'failed_products' => $this->failed_products,
            'indexed_products' => $this->indexed_products,
            'error_message' => $this->error_message,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'duration' => $this->calculateDuration(),
            'progress_percentage' => $this->calculateProgressPercentage(),
        ];
    }

    /**
     * Calculate the duration of the sync operation in seconds.
     */
    protected function calculateDuration(): ?int
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }

        return $this->started_at->diffInSeconds($this->completed_at);
    }

    /**
     * Calculate the progress percentage.
     */
    protected function calculateProgressPercentage(): ?float
    {
        if (!$this->total_products || $this->total_products === 0) {
            return null;
        }

        return round(($this->processed_products / $this->total_products) * 100, 1);
    }
}
