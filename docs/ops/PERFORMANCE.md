# AgencySync Performance Monitoring

This document covers performance monitoring, cache optimization, and query tuning for AgencySync deployments.

## Cache Monitoring

### Redis Cache Hit Rate

**Check cache hit rate:**
```bash
docker-compose exec redis redis-cli INFO stats | grep keyspace
```

**View cache keys:**
```bash
# All agency cache keys
docker-compose exec redis redis-cli KEYS "agency:*"

# Dashboard metrics cache
docker-compose exec redis redis-cli GET "agency:dashboard:metrics:{tenant-uuid}"

# Tenant list cache
docker-compose exec redis redis-cli GET "agency:tenants:list"
```

**Monitor cache size:**
```bash
# Redis memory usage
docker-compose exec redis redis-cli INFO memory | grep used_memory_human

# Number of cached keys
docker-compose exec redis redis-cli DBSIZE
```

**Cache TTL optimization:**
- Dashboard metrics: 5 minutes (300s) — balances freshness and performance
- Tenant list: 15 minutes (900s) — rarely changes
- Global metrics: 10 minutes (600s) — moderate change frequency

**Signs of cache issues:**
- High database query count
- Slow dashboard loads (>2 seconds)
- Redis keys not expiring (runaway memory growth)

### Cache Warming Strategy

**After deployment:**
```bash
# Warm all caches
docker-compose exec app php artisan cache:warm
```

**For specific tenants:**
```bash
# Warm single tenant
docker-compose exec app php artisan cache:warm --tenant={uuid}
```

**Schedule cache warming** (add to crontab):
```bash
# Warm cache every hour
0 * * * * cd /path/to/agencysync && docker-compose exec -T app php artisan cache:warm
```

## Database Performance

### Slow Query Detection

**Enable slow query log:**
```sql
-- In MySQL container
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1; -- Log queries >1 second
```

**View slow queries:**
```bash
docker-compose exec mysql tail -f /var/log/mysql/slow.log
```

**Common slow queries:**
- Missing indexes on tenant_id columns
- N+1 queries from missing eager loading
- Large JOIN operations without pagination

### Index Optimization

**Check index usage:**
```sql
-- In MySQL
EXPLAIN SELECT * FROM products WHERE tenant_id = 'uuid';

-- Should show "key" column using index
```

**Required indexes:**
```sql
-- Tenant scoping
CREATE INDEX idx_products_tenant_id ON products(tenant_id);
CREATE INDEX idx_sync_logs_tenant_id ON sync_logs(tenant_id);
CREATE INDEX idx_job_statuses_tenant_id ON job_statuses(tenant_id);

-- Dashboard metrics
CREATE INDEX idx_sync_logs_created_at ON sync_logs(created_at DESC);

-- Search optimization
CREATE INDEX idx_products_name_sku ON products(name, sku);
```

**Verify indexes exist:**
```bash
docker-compose exec mysql mysql -u root -p{password} agencysync -e "SHOW INDEX FROM products;"
```

### Query Optimization

**Use eager loading:**
```php
// Bad (N+1 queries)
$logs = SyncLog::all();
foreach ($logs as $log) {
    echo $log->tenant->name; // Separate query per log
}

// Good (2 queries)
$logs = SyncLog::with('tenant')->get();
foreach ($logs as $log) {
    echo $log->tenant->name; // No additional queries
}
```

**Select specific columns:**
```php
// Bad (fetches all columns)
$tenants = Tenant::all();

// Good (fetches only needed columns)
$tenants = Tenant::select(['id', 'name', 'slug', 'status'])->get();
```

**Use chunking for large datasets:**
```php
// Bad (loads all into memory)
Product::all()->each(function ($product) {
    // Process product
});

// Good (processes 1000 at a time)
Product::chunk(1000, function ($products) {
    foreach ($products as $product) {
        // Process product
    }
});
```

## Elasticsearch Performance

### Cluster Health Monitoring

**Check cluster health:**
```bash
docker-compose exec elasticsearch curl -X GET "localhost:9200/_cluster/health?pretty"
```

**Health status meanings:**
- **Green** — All shards allocated (optimal)
- **Yellow** — Replicas not allocated (acceptable for single-node)
- **Red** — Some shards not allocated (critical)

**Monitor index stats:**
```bash
docker-compose exec elasticsearch curl -X GET "localhost:9200/agencysync/_stats?pretty"
```

**Check query performance:**
```bash
# Enable slow log
docker-compose exec elasticsearch curl -X PUT "localhost:9200/agencysync/_settings" -H 'Content-Type: application/json' -d '{
  "index.search.slowlog.threshold.query.warn": "1s",
  "index.search.slowlog.threshold.query.info": "500ms"
}'
```

**Optimization tips:**
- Use tenant-specific indexes (agencysync_{tenant_id})
- Limit search result size (pagination max 100 per page)
- Cache frequent search terms
- Optimize mapping for search fields

### Index Optimization

**Reindex products:**
```bash
docker-compose exec app php artisan scout:import "App\Models\Product"
```

**Check index size:**
```bash
docker-compose exec elasticsearch curl -X GET "localhost:9200/_cat/indices?v"
```

**Optimize index:**
```bash
docker-compose exec elasticsearch curl -X POST "localhost:9200/agencysync/_forcemerge?max_num_segments=1"
```

## Queue Worker Optimization

### Queue Backlog Monitoring

**Check queue size:**
```bash
docker-compose exec app php artisan tinker
>>> Queue::size()
```

**Check failed jobs:**
```bash
docker-compose exec app php artisan queue:failed
```

**Monitor worker status:**
```bash
docker-compose exec app supervisorctl status
```

**Optimization tips:**
- Scale workers based on queue size (2 workers default)
- Use dedicated queues for heavy jobs (exports vs syncs)
- Set appropriate timeout values (120s default)
- Implement retry logic with exponential backoff

### Worker Memory Management

**Set memory limit:**
```ini
; In docker/supervisor/worker.conf
[program:worker]
command=php artisan queue:work --sleep=3 --tries=3 --max-time=3600 --timeout=120
process_name=%(program_name)s_%(process_num)02d
autostart=true
autorestart=true
user=root
redirect_stderr=true
stdout_logfile=/var/log/supervisor/worker.log
stopwaitsecs=3600
```

**Monitor memory usage:**
```bash
docker stats app
```

**Restart workers on memory limit:**
```ini
; Add to worker.conf
stopasgroup=true
killasgroup=true
```

## Application Performance

### Response Time Monitoring

**Measure API response times:**
```bash
# Use curl with timing
curl -w "@curl-format.txt" -o /dev/null -s "http://localhost/api/v1/tenants"

# curl-format.txt contents:
# time_namelookup:  %{time_namelookup}\n
# time_connect:     %{time_connect}\n
# time_appconnect:  %{time_appconnect}\n
# time_pretransfer: %{time_pretransfer}\n
# time_starttransfer: %{time_starttransfer}\n
# time_total:       %{time_total}\n
```

**Target response times:**
- Dashboard metrics: <500ms (cached)
- Tenant list: <300ms (cached)
- Product search: <500ms (Elasticsearch)
- Sync log list: <300ms (paginated)

### Memory Profiling

**Check memory usage:**
```bash
# Container memory
docker stats app --no-stream

# PHP memory limit
docker-compose exec app php -i | grep memory_limit
```

**Profile specific routes:**
```php
// Add to controller
$startMemory = memory_get_usage();
// ... code ...
$endMemory = memory_get_usage();
Log::info("Memory used: " . ($endMemory - $startMemory) . " bytes");
```

### Laravel Telescope (Optional)

**Install for development:**
```bash
docker-compose exec app composer require laravel/telescope --dev
docker-compose exec app php artisan telescope:install
docker-compose exec app php artisan migrate
```

**Monitor:**
- Requests (response times, queries)
- Exceptions (error tracking)
- Jobs (queue performance)
- Queries (slow query detection)

**Access dashboard:**
```bash
# Visit /telescope in browser
# Or publish assets:
docker-compose exec app php artisan telescope:publish
```

## Docker Performance

### Container Resource Limits

**Check current limits:**
```bash
docker-compose config | grep -A 5 "deploy:"
```

**Set resource limits:**
```yaml
# In docker-compose.yml
services:
  app:
    deploy:
      resources:
        limits:
          cpus: '1.0'
          memory: 2G
        reservations:
          cpus: '0.5'
          memory: 1G

  elasticsearch:
    deploy:
      resources:
        limits:
          memory: 1G

  mysql:
    deploy:
      resources:
        limits:
          memory: 512M
```

### Network Performance

**Check network latency:**
```bash
# App to MySQL
docker-compose exec app ping mysql

# App to Redis
docker-compose exec app ping redis

# App to Elasticsearch
docker-compose exec app ping elasticsearch
```

**Optimize network:**
- Use Docker internal network (not localhost)
- Minimize cross-container calls
- Cache responses locally

## Performance Benchmarks

**Target metrics (v1.0):**
- Dashboard load (cached): <500ms
- Product search (1000 results): <500ms
- Tenant list (cached): <300ms
- Export job (100K rows): <5 minutes
- Sync job (1000 products): <2 minutes

**Current performance:**
```bash
# Run benchmark script
docker-compose exec app php artisan benchmark
```

(Create custom benchmark command for your use case)

## Optimization Checklist

**Daily:**
- [ ] Check cache hit rates
- [ ] Monitor queue backlog
- [ ] Review error logs

**Weekly:**
- [ ] Review slow query log
- [ ] Check Elasticsearch cluster health
- [ ] Monitor memory usage trends

**Monthly:**
- [ ] Review and optimize indexes
- [ ] Analyze cache TTL effectiveness
- [ ] Benchmark critical paths

---

**Related Documentation:**
- [LOGGING.md](LOGGING.md) — Log file locations and formats
- [TROUBLESHOOTING.md](TROUBLESHOOTING.md) — Common issues and solutions

**Last Updated:** 2026-03-14
