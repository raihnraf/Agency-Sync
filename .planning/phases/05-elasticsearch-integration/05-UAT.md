---
status: testing
phase: 05-elasticsearch-integration
source: codebase-analysis
started: 2025-03-13T16:45:00Z
updated: 2025-03-13T16:45:00Z
---

## Current Test
number: 1
name: Cold Start Smoke Test
expected: |
  Stop all running Docker containers (docker compose down). Remove any Elasticsearch volumes if present. Start fresh with docker compose up -d. Verify all services start successfully: app, nginx, mysql, redis, and elasticsearch containers are running. Elasticsearch health check passes (curl http://localhost:9200/_cluster/health returns green status). Application can connect to Elasticsearch without errors.
awaiting: user response

## Tests

### 1. Cold Start Smoke Test
expected: Stop all running Docker containers (docker compose down). Remove any Elasticsearch volumes if present. Start fresh with docker compose up -d. Verify all services start successfully: app, nginx, mysql, redis, and elasticsearch containers are running. Elasticsearch health check passes (curl http://localhost:9200/_cluster/health returns green status). Application can connect to Elasticsearch without errors.
result: pending

### 2. Elasticsearch Container is Running
expected: Docker container named "agency-sync-elasticsearch" exists and is in running state. Container is healthy (healthcheck passes). Elasticsearch is accessible on port 9200.
result: pending

### 3. Product Search API - Basic Search
expected: Create a product with name "Wireless Bluetooth Headphones". Make an authenticated GET request to /api/v1/tenants/{tenantId}/search?query=headphones. API returns the product in results array with 200 status. Response includes pagination metadata (total, current_page, per_page).
result: pending

### 4. Product Search API - Fuzzy Matching
expected: Create a product with name "Samsung Galaxy S24 Ultra". Search for "samsun gallaxy s24" (with typos). API returns the Samsung product despite typos. Search for "galxy" also returns the product. Results are ordered by relevance score.
result: pending

### 5. Product Search API - Multi-Field Search
expected: Create product with name "Premium Coffee Maker" and description "Programmable drip coffee maker with thermal carafe". Search for "thermal" (word in description). Search for "programmable" (word in description). Both searches return the coffee maker. Name matches rank higher than description matches.
result: pending

### 6. Tenant Isolation - Separate Indices
expected: Create tenant A with product "Laptop X". Create tenant B with product "Laptop Y". Search for "laptop" while authenticated as tenant A. Results only show Laptop X (tenant A's product), not Laptop Y. Verify Elasticsearch indices: products_tenant_{A} and products_tenant_{B} exist separately.
result: pending

### 7. Async Indexing - Product Creation
expected: Create a new product via API. Verify IndexProductJob is dispatched to 'indexing' queue. Wait for job to process (check Laravel Horizon or run queue:work). Search for the product name. Product appears in search results. Background job logs show successful indexing.
result: pending

### 8. Async Indexing - Product Update
expected: Update an existing product's name from "Old Name" to "New Product Name". Wait for indexing job to process. Search for "New Product Name". Updated product appears in results with new name. Old name no longer returns the product.
result: pending

### 9. Async Indexing - Product Deletion
expected: Delete a product. Verify DeleteFromIndexJob is dispatched. Wait for job to process. Search for the deleted product name. Product no longer appears in search results.
result: pending

### 10. Search Index Status Endpoint
expected: Make authenticated GET request to /api/v1/tenants/{tenantId}/search/status. API returns 200 status with index_exists (boolean) and index_name (e.g., "products_tenant_123"). Response includes tenant_id.
result: pending

### 11. Reindex Endpoint
expected: Create 5 products for a tenant. Delete their Elasticsearch index manually. Make authenticated POST request to /api/v1/tenants/{tenantId}/search/reindex. API returns 200 with "Reindex completed successfully" message. Search returns all 5 products. Index is recreated.
result: pending

### 12. Search Pagination
expected: Create 25 products in a tenant. Search with query parameter page=1&per_page=10. API returns first 10 products. Total shows 25. Last_page shows 3. Request page=2. Returns next 10 products. Page=3 returns last 5 products.
result: pending

## Summary

total: 12
passed: 0
issues: 0
pending: 12
skipped: 0

## Gaps

[none yet]
