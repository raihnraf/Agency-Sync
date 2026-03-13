<?php

/**
 * Test Bootstrap with Elasticsearch Client Factory
 * 
 * Provides test isolation helpers for Elasticsearch integration tests.
 * Uses the existing Elasticsearch container from Docker Compose.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;

/**
 * Create Elasticsearch client for tests
 * 
 * @return Client
 * @throws \Exception If connection fails
 */
function createElasticsearchClientForTests(): Client
{
    $host = $_ENV['ELASTICSEARCH_HOST'] ?? 'localhost';
    $port = $_ENV['ELASTICSEARCH_PORT'] ?? '9200';
    
    $client = ClientBuilder::create()
        ->setHosts(["{$host}:{$port}"])
        ->build();
    
    // Verify connection (fail fast)
    try {
        $client->ping();
    } catch (\Exception $e) {
        throw new \Exception("Failed to connect to Elasticsearch at {$host}:{$port}: " . $e->getMessage());
    }
    
    return $client;
}

/**
 * Create test index in Elasticsearch
 * 
 * @param string $indexName
 * @return void
 */
function createTestIndex(string $indexName): void
{
    $client = createElasticsearchClientForTests();
    
    // Delete if exists
    try {
        $client->indices()->delete(['index' => $indexName]);
    } catch (\Exception $e) {
        // Index may not exist, ignore
    }
    
    // Create with basic settings
    $client->indices()->create([
        'index' => $indexName,
        'body' => [
            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 0,
            ],
        ],
    ]);
}

/**
 * Delete test index from Elasticsearch
 * 
 * @param string $indexName
 * @return void
 */
function deleteTestIndex(string $indexName): void
{
    $client = createElasticsearchClientForTests();
    
    try {
        $client->indices()->delete(['index' => $indexName]);
    } catch (\Exception $e) {
        // Index may not exist, ignore
    }
}
