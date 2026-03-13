<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\PlatformType;
use App\Enums\SyncStatus;

class SyncLog extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'platform_type',
        'status',
        'started_at',
        'completed_at',
        'total_products',
        'processed_products',
        'failed_products',
        'error_message',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform_type' => PlatformType::class,
            'status' => SyncStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * The tenant that owns the sync log.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Mark the sync as running.
     */
    public function markAsRunning(): void
    {
        $this->update([
            'status' => SyncStatus::RUNNING,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark the sync as completed.
     */
    public function markAsCompleted(int $total, int $processed, int $failed): void
    {
        $this->update([
            'status' => $failed > 0 ? SyncStatus::PARTIALLY_FAILED : SyncStatus::COMPLETED,
            'completed_at' => now(),
            'total_products' => $total,
            'processed_products' => $processed,
            'failed_products' => $failed,
        ]);
    }

    /**
     * Mark the sync as failed.
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => SyncStatus::FAILED,
            'completed_at' => now(),
            'error_message' => $error,
        ]);
    }

    /**
     * Increment the processed products counter.
     */
    public function incrementProcessed(): void
    {
        $this->increment('processed_products');
    }

    /**
     * Increment the failed products counter and optionally log error.
     */
    public function incrementFailed(string $error = null): void
    {
        $this->increment('failed_products');

        if ($error) {
            $metadata = $this->metadata ?? [];
            $errors = $metadata['errors'] ?? [];
            $errors[] = [
                'error' => $error,
                'timestamp' => now()->toIso8601String(),
            ];
            $this->update(['metadata' => array_merge($metadata, ['errors' => $errors])]);
        }
    }
}
