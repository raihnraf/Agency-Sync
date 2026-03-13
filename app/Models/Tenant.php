<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Enums\PlatformType;
use App\Enums\TenantStatus;

class Tenant extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'platform_type',
        'platform_url',
        'status',
        'api_credentials',
        'settings',
        'last_sync_at',
        'sync_status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'api_credentials',
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
            'status' => TenantStatus::class,
            'api_credentials' => 'encrypted:json',
            'settings' => 'array',
            'last_sync_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Add global scope for tenant filtering
        static::addGlobalScope('tenant', function ($builder) {
            if (auth()->check() && ($tenantId = auth()->user()->currentTenantId())) {
                $builder->where('tenants.id', $tenantId);
            }
        });

        static::creating(function (Tenant $tenant) {
            if (empty($tenant->slug)) {
                $tenant->slug = Str::slug($tenant->name);
            }
        });
    }

    /**
     * The users that belong to the tenant.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withTimestamps()
            ->withPivot('role', 'joined_at');
    }

    /**
     * Get the sync logs for the tenant.
     */
    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }

    /**
     * Set the current tenant for queue jobs and background processing.
     */
    public static function setCurrent(?Tenant $tenant): void
    {
        if ($tenant) {
            app()->instance('currentTenant', $tenant);
        } else {
            app()->forgetInstance('currentTenant');
        }
    }

    /**
     * Set the current tenant for queue jobs and background processing.
     */
    public static function setCurrentTenant(?Tenant $tenant): void
    {
        self::setCurrent($tenant);
    }

    /**
     * Get the current tenant for queue jobs and background processing.
     */
    public static function currentTenant(): ?Tenant
    {
        if (app()->bound('currentTenant')) {
            return app('currentTenant');
        }
        return null;
    }

    /**
     * Clear the current tenant context.
     */
    public static function clearCurrent(): void
    {
        app()->forgetInstance('currentTenant');
    }
}
