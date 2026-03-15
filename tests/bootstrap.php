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

/**
 * Shared test fixtures for audit log tests
 *
 * Provides common setup helpers for SyncLogDetailsTest, StackTraceCaptureTest,
 * SuccessSyncDetailsTest, and other audit log related tests.
 */

/**
 * Create a test user with authentication token
 *
 * @return \App\Models\User
 */
function createTestUserWithToken(): \App\Models\User
{
    $user = \App\Models\User::factory()->create();
    $user->createToken('test-token');
    return $user;
}

/**
 * Create a test tenant with platform credentials
 *
 * @param array $overrides
 * @return \App\Models\Tenant
 */
function createTestTenant(array $overrides = []): \App\Models\Tenant
{
    return \App\Models\Tenant::factory()->create($overrides);
}

/**
 * Create a test sync log with various states
 *
 * @param array $overrides
 * @return \App\Models\SyncLog
 */
function createTestSyncLog(array $overrides = []): \App\Models\SyncLog
{
    return \App\Models\SyncLog::factory()->create($overrides);
}

/**
 * Create a failed sync log with error details
 *
 * @param array $errorDetails
 * @return \App\Models\SyncLog
 */
function createFailedSyncLog(array $errorDetails = []): \App\Models\SyncLog
{
    return \App\Models\SyncLog::factory()->failed()->create([
        'error_details' => array_merge([
            'type' => 'api_error',
            'message' => 'Test error message',
            'code' => 500,
        ], $errorDetails)
    ]);
}

/**
 * Create a successful sync log with products summary
 *
 * @param array $summary
 * @return \App\Models\SyncLog
 */
function createSuccessSyncLog(array $summary = []): \App\Models\SyncLog
{
    return \App\Models\SyncLog::factory()->create([
        'status' => 'completed',
        'metadata' => array_merge([
            'products_summary' => [
                'total' => 100,
                'processed' => 95,
                'failed' => 5,
                'indexed' => 90,
            ],
            'duration' => 45.2,
        ], $summary)
    ]);
}
