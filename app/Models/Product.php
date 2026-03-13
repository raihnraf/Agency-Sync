<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

/**
 * Product Model
 * 
 * Multi-tenant product with Elasticsearch indexing via Scout.
 * Each product belongs to a tenant and is indexed in tenant-specific ES index.
 */
class Product extends Model
{
    use HasFactory, SoftDeletes, Searchable;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'external_id',
        'name',
        'slug',
        'description',
        'sku',
        'price',
        'compare_at_price',
        'stock_quantity',
        'platform',
        'platform_product_id',
        'platform_data',
        'metadata',
        'last_synced_at',
    ];

    protected $casts = [
        'price' => 'float',
        'compare_at_price' => 'float',
        'stock_quantity' => 'integer',
        'platform' => 'string',
        'platform_data' => 'array',
        'metadata' => 'array',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Boot the model
     */
    protected static function boot(): void
    {
        parent::boot();

        // Add global scope for tenant filtering
        static::addGlobalScope('tenant', function ($builder) {
            if (app()->bound('currentTenant')) {
                $tenant = app('currentTenant');
                if ($tenant) {
                    $builder->where('products.tenant_id', $tenant->id);
                }
            }
        });

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::uuid();
            }

            if (empty($model->slug) && !empty($model->name)) {
                $model->slug = \Illuminate\Support\Str::slug($model->name);
            }
        });
    }

    /**
     * Get the tenant that owns the product
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the index name for the model
     */
    public function searchableAs(): string
    {
        return 'products_tenant_' . $this->tenant_id;
    }

    /**
     * Get the value used to index the model
     */
    public function getScoutKey(): string
    {
        return $this->id;
    }

    /**
     * Get the key name used to index the model
     */
    public function getScoutKeyName(): string
    {
        return 'id';
    }

    /**
     * Determine if the model should be searchable
     */
    public function shouldBeSearchable(): bool
    {
        // Only index if belongs to a tenant
        return !empty($this->tenant_id);
    }

    /**
     * Get the array representation for Elasticsearch
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'sku' => $this->sku,
            'price' => (float) $this->price,
            'stock_quantity' => $this->stock_quantity,
            'platform_product_id' => $this->platform_product_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
