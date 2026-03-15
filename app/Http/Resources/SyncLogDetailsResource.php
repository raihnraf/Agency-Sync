<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SyncLog
 */
class SyncLogDetailsResource extends JsonResource
{
    /**
     * Disable data wrapper.
     *
     * @var null
     */
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'platform_type' => $this->platform_type?->value,
            'status' => $this->status?->value,
            'error_message' => $this->error_message,
            'metadata' => $this->metadata ?? [],
            'error_details' => $this->extractErrorDetails(),
            'tenant' => $this->whenLoaded('tenant', function () {
                return [
                    'id' => $this->tenant->id,
                    'name' => $this->tenant->name,
                    'platform_type' => $this->tenant->platform_type?->value,
                ];
            }),
            'products_summary' => [
                'total' => $this->total_products,
                'processed' => $this->processed_products,
                'failed' => $this->failed_products,
                'indexed' => $this->indexed_products,
            ],
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'duration_seconds' => $this->calculateDuration(),
        ];
    }

    /**
     * Extract error details from metadata.
     *
     * @return array<string, mixed>|null
     */
    protected function extractErrorDetails(): ?array
    {
        return $this->metadata['error_details'] ?? null;
    }

    /**
     * Calculate sync duration in seconds.
     *
     * @return int|null
     */
    protected function calculateDuration(): ?int
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }

        return $this->started_at->diffInSeconds($this->completed_at);
    }
}
