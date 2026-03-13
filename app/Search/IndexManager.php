<?php

namespace App\Search;

use App\Models\Tenant;
use Elastic\Elasticsearch\Client;

/**
 * Index management service for multi-tenant Elasticsearch
 * 
 * Manages index-per-tenant strategy for complete data isolation.
 * Each tenant gets their own index: products_tenant_{tenant_id}
 */
class IndexManager
{
    protected Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Get the index name for a tenant
     */
    public function getIndexName(Tenant $tenant): string
    {
        return 'products_tenant_' . $tenant->id;
    }

    /**
     * Create index for a tenant with proper mappings
     */
    public function createIndex(Tenant $tenant): void
    {
        $indexName = $this->getIndexName($tenant);

        // Delete if exists
        $this->deleteIndex($tenant);

        $this->client->indices()->create([
            'index' => $indexName,
            'body' => [
                'settings' => $this->getIndexSettings(),
                'mappings' => [
                    'properties' => $this->getProductMappings(),
                ],
            ],
        ]);
    }

    /**
     * Delete index for a tenant
     */
    public function deleteIndex(Tenant $tenant): void
    {
        $indexName = $this->getIndexName($tenant);

        try {
            $this->client->indices()->delete(['index' => $indexName]);
        } catch (\Exception $e) {
            // Index may not exist, ignore
        }
    }

    /**
     * Check if index exists for tenant
     */
    public function indexExists(Tenant $tenant): bool
    {
        return $this->client->indices()->exists([
            'index' => $this->getIndexName($tenant),
        ]);
    }

    /**
     * Get index settings for product search
     */
    public function getIndexSettings(): array
    {
        return [
            'number_of_shards' => 1,
            'number_of_replicas' => 0,
            'analysis' => [
                'analyzer' => [
                    'product_analyzer' => [
                        'type' => 'custom',
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase', 'asciifolding'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get product field mappings
     */
    public function getProductMappings(): array
    {
        return [
            'id' => ['type' => 'keyword'],
            'tenant_id' => ['type' => 'keyword'],
            'name' => [
                'type' => 'text',
                'analyzer' => 'product_analyzer',
                'fields' => [
                    'keyword' => ['type' => 'keyword'],
                ],
            ],
            'slug' => ['type' => 'keyword'],
            'description' => [
                'type' => 'text',
                'analyzer' => 'product_analyzer',
            ],
            'sku' => ['type' => 'keyword'],
            'price' => ['type' => 'scaled_float', 'scaling_factor' => 100],
            'stock_quantity' => ['type' => 'integer'],
            'platform_product_id' => ['type' => 'keyword'],
            'created_at' => ['type' => 'date'],
            'updated_at' => ['type' => 'date'],
        ];
    }
}
