<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'platform_type' => $this->platform_type->value,
            'platform_url' => $this->platform_url,
            'status' => $this->status->value,
            'settings' => $this->settings ?? [],
            'last_sync_at' => $this->last_sync_at?->toISOString(),
            'sync_status' => $this->sync_status,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }

    /**
     * Disable data wrapper.
     *
     * @var null
     */
    public static $wrap = null;
}
