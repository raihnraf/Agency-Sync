<?php

namespace App\Engines;

use App\Models\Tenant;
use App\Search\IndexManager;
use Elastic\Elasticsearch\Client;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;

/**
 * Custom Elasticsearch 8.x Engine for Laravel Scout
 * 
 * Implements index-per-tenant strategy for multi-tenant isolation.
 * Supports fuzzy matching, multi-match queries, and relevance scoring.
 */
class ElasticsearchEngine extends Engine
{
    protected Client $client;
    protected IndexManager $indexManager;

    public function __construct(Client $client, IndexManager $indexManager)
    {
        $this->client = $client;
        $this->indexManager = $indexManager;
    }

    /**
     * Update models in the index
     */
    public function update($models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $tenant = $this->getCurrentTenant();
        if (!$tenant) {
            return;
        }

        // Ensure index exists
        if (!$this->indexManager->indexExists($tenant)) {
            $this->indexManager->createIndex($tenant);
        }

        $indexName = $this->indexManager->getIndexName($tenant);
        $bulk = ['body' => []];

        foreach ($models as $model) {
            $bulk['body'][] = [
                'index' => [
                    '_index' => $indexName,
                    '_id' => $model->getScoutKey(),
                ],
            ];
            $bulk['body'][] = $this->toSearchableArray($model);
        }

        if (!empty($bulk['body'])) {
            $this->client->bulk($bulk);
            $this->client->indices()->refresh(['index' => $indexName]);
        }
    }

    /**
     * Remove models from the index
     */
    public function delete($models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $tenant = $this->getCurrentTenant();
        if (!$tenant) {
            return;
        }

        $indexName = $this->indexManager->getIndexName($tenant);
        $bulk = ['body' => []];

        foreach ($models as $model) {
            $bulk['body'][] = [
                'delete' => [
                    '_index' => $indexName,
                    '_id' => $model->getScoutKey(),
                ],
            ];
        }

        if (!empty($bulk['body'])) {
            $this->client->bulk($bulk);
        }
    }

    /**
     * Perform search against Elasticsearch
     */
    public function search(Builder $builder): array
    {
        $tenant = $this->getCurrentTenant();
        if (!$tenant) {
            return ['hits' => ['hits' => [], 'total' => ['value' => 0]]];
        }

        $indexName = $this->indexManager->getIndexName($tenant);

        $params = [
            'index' => $indexName,
            'body' => [
                'query' => $this->buildQuery($builder),
                'sort' => $this->buildSort($builder),
                'from' => $this->getFrom($builder),
                'size' => $builder->limit ?? 20,
            ],
        ];

        return $this->client->search($params)->asArray();
    }

    /**
     * Perform paginated search
     */
    public function paginate(Builder $builder, $perPage, $page): array
    {
        $builder->limit = $perPage;
        $builder->offset = ($page - 1) * $perPage;

        return $this->search($builder);
    }

    /**
     * Pluck and return the primary keys of the given results
     */
    public function mapIds($results): \Illuminate\Support\Collection
    {
        if (empty($results['hits']['hits'])) {
            return collect();
        }

        return collect($results['hits']['hits'])->pluck('_id')->values();
    }

    /**
     * Map the given results to instances of the given model
     */
    public function map(Builder $builder, $results, $model): Collection
    {
        if (empty($results['hits']['hits'])) {
            return $model->newCollection();
        }

        $hits = $results['hits']['hits'];
        $ids = collect($hits)->pluck('_id')->values()->all();

        // Get models in the same order as ES relevance
        $models = $model->getScoutModelsByIds($builder, $ids)
            ->keyBy($model->getScoutKeyName());

        return $model->newCollection(
            collect($hits)->map(function ($hit) use ($models) {
                $id = $hit['_id'];
                return $models->get($id);
            })->filter()->values()->all()
        );
    }

    /**
     * Map the given results to instances of the given model via lazy collection
     */
    public function lazyMap(Builder $builder, $results, $model): LazyCollection
    {
        if (empty($results['hits']['hits'])) {
            return LazyCollection::make($model->newCollection());
        }

        $hits = $results['hits']['hits'];
        $ids = collect($hits)->pluck('_id')->values()->all();

        // Get models in the same order as ES relevance
        $models = $model->getScoutModelsByIds($builder, $ids)
            ->keyBy($model->getScoutKeyName());

        return LazyCollection::make(
            collect($hits)->map(function ($hit) use ($models) {
                $id = $hit['_id'];
                return $models->get($id);
            })->filter()->values()->all()
        );
    }

    /**
     * Get total count from search results
     */
    public function getTotalCount($results): int
    {
        return $results['hits']['total']['value'] ?? 0;
    }

    /**
     * Flush all models from the index
     */
    public function flush($model): void
    {
        $tenant = $this->getCurrentTenant();
        if (!$tenant) {
            return;
        }

        $this->indexManager->deleteIndex($tenant);
    }

    /**
     * Create a search index (Scout Engine interface)
     */
    public function createIndex($name, array $options = []): void
    {
        // This is called by Scout with a string name
        // For our multi-tenant approach, we handle this in IndexManager
    }

    /**
     * Delete a search index (Scout Engine interface)
     */
    public function deleteIndex($name): void
    {
        // This is called by Scout with a string name
        // For our multi-tenant approach, we handle this in IndexManager
    }

    /**
     * Build Elasticsearch query from Scout builder
     */
    protected function buildQuery(Builder $builder): array
    {
        $query = $builder->query;

        // Multi-match query with fuzziness
        return [
            'bool' => [
                'must' => [
                    'multi_match' => [
                        'query' => $query,
                        'fields' => ['name^3', 'description^2', 'sku'],
                        'type' => 'best_fields',
                        'fuzziness' => 'AUTO',
                        'prefix_length' => 3,
                    ],
                ],
            ],
        ];
    }

    /**
     * Build sort configuration
     */
    protected function buildSort(Builder $builder): array
    {
        // Default sort by relevance (_score)
        if (empty($builder->orders)) {
            return ['_score' => 'desc'];
        }

        return collect($builder->orders)->map(function ($order) {
            return [$order['column'] => $order['direction'] ?? 'asc'];
        })->all();
    }

    /**
     * Get offset for pagination
     */
    protected function getFrom(Builder $builder): int
    {
        return $builder->offset ?? 0;
    }

    /**
     * Convert model to searchable array
     */
    protected function toSearchableArray(Model $model): array
    {
        $array = $model->toSearchableArray();
        $array['tenant_id'] = $model->tenant_id;
        return $array;
    }

    /**
     * Get current tenant from authenticated user
     */
    protected function getCurrentTenant(): ?Tenant
    {
        $user = Auth::user();
        
        if (!$user) {
            return null;
        }

        // Try to get current tenant from user
        return $user->currentTenant;
    }

    /**
     * Create index for tenant (convenience method)
     */
    public function createIndexForTenant(Tenant $tenant): void
    {
        $this->indexManager->createIndex($tenant);
    }

    /**
     * Delete index for tenant (convenience method)
     */
    public function deleteIndexForTenant(Tenant $tenant): void
    {
        $this->indexManager->deleteIndex($tenant);
    }
}
