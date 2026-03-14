# AgencySync Troubleshooting

This document covers common errors, diagnostic steps, and solutions for AgencySync deployment issues.

## Sync Operation Failures

### Issue: Sync job stuck in "running" status

**Symptoms:**
- Job status shows "running" for extended period
- No progress in products_synced count
- Queue worker not processing new jobs

**Diagnosis:**
```bash
# Check queue worker status
docker-compose ps supervisor

# Check worker logs
docker-compose logs -f supervisor

# Check job status in database
docker-compose exec app php artisan tinker
>>> App\Models\JobStatus::where('status', 'running')->get()
```

**Solutions:**

1. **Restart queue workers**
   ```bash
   docker-compose restart supervisor
   ```

2. **Check for stuck jobs**
   ```bash
   docker-compose exec app php artisan queue:retry all
   ```

3. **Clear stuck jobs**
   ```bash
   docker-compose exec app php artisan tinker
   >>> App\Models\JobStatus::where('status', 'running')
   ...    ->where('created_at', '<', now()->subHour())
   ...    ->update(['status' => 'failed', 'error_message' => 'Timeout'])
   ```

**Prevention:**
- Set `$timeout = 120` (2 minutes) on jobs
- Configure Supervisor to restart workers after memory limit
- Monitor queue backlog: `Queue::size()`

### Issue: Sync fails with "Shopify API rate limit exceeded"

**Symptoms:**
- Job marked as failed
- Error message: "429 Too Many Requests"
- Sync log shows partial progress

**Diagnosis:**
```bash
# Check sync log error message
docker-compose exec app php artisan tinker
>>> App\Models\SyncLog::latest()->first()->error_message

# Check job failure details
>>> App\Models\JobStatus::where('status', 'failed')->latest()->first()->error_message
```

**Solutions:**

1. **Wait and retry** (rate limit resets every second)
   ```bash
   docker-compose exec app php artisan queue:retry all
   ```

2. **Increase rate limiting delay** (in SyncProductsJob)
   ```php
   // Add to sync service
   usleep(500000); // 0.5 second delay per product
   ```

3. **Reduce batch size**
   ```php
   // Sync products in smaller batches
   ShopifyService::fetchProducts(['limit' => 50]); // default 250
   ```

**Prevention:**
- Implement exponential backoff in sync service
- Monitor Shopify API usage in admin dashboard
- Schedule syncs during off-peak hours

### Issue: Sync fails with "invalid API credentials"

**Symptoms:**
- Job marked as failed immediately
- Error: "401 Unauthorized" or "403 Forbidden"
- Sync log shows authentication error

**Diagnosis:**
```bash
# Check tenant credentials
docker-compose exec app php artisan tinker
>>> $tenant = App\Models\Tenant::first()
>>> $tenant->api_credentials  // Returns encrypted null if missing
```

**Solutions:**

1. **Update tenant credentials**
   ```bash
   # Via admin dashboard
   Visit /dashboard/tenants/{id}/edit
   Update API credentials fields
   ```

2. **Verify API access** (via curl)
   ```bash
   # Test Shopify API
   curl -X GET "https://{shop}.myshopify.com/admin/api/2024-01/products.json" \
     -H "X-Shopify-Access-Token: {token}"
   ```

**Prevention:**
- Validate credentials on tenant creation
- Implement credential rotation policy
- Monitor API authentication failures

## Queue Worker Issues

### Issue: Queue workers not processing jobs

**Symptoms:**
- Jobs stuck in "pending" status
- Queue size growing: `Queue::size()` returns high number
- No worker activity in logs

**Diagnosis:**
```bash
# Check Supervisor status
docker-compose ps supervisor

# Check worker logs
docker-compose logs supervisor | tail -n 50

# Check queue depth
docker-compose exec app php artisan tinker
>>> Illuminate\Support\Facades\Queue::size()
```

**Solutions:**

1. **Restart Supervisor**
   ```bash
   docker-compose restart supervisor
   ```

2. **Check Supervisor configuration**
   ```bash
   docker-compose exec app supervisorctl status
   ```

3. **Verify Redis connection**
   ```bash
   docker-compose exec app php artisan tinker
   >>> Redis::connection()->ping()
   ```

**Prevention:**
- Monitor queue size in dashboard
- Set up alerts for queue backlog
- Configure auto-scaling for workers (future)

### Issue: Queue workers consuming too much memory

**Symptoms:**
- Workers restart frequently
- Docker container OOM errors
- Slow job processing

**Diagnosis:**
```bash
# Check container memory usage
docker stats app

# Check worker memory limit
docker-compose exec app cat /etc/supervisor/conf.d/worker.conf | grep memory_limit
```

**Solutions:**

1. **Reduce Supervisor memory limit**
   ```ini
   ; In docker/supervisor/worker.conf
   supervisorctl stop worker:*
   ```

2. **Implement job chunking** (for memory-intensive jobs)
   ```php
   // In ExportProductCatalog job
   Product::chunk(1000, function ($products) {
       // Process 1000 products at a time
   });
   ```

3. **Increase Docker memory limit**
   ```yaml
   # In docker-compose.yml
   services:
     app:
       deploy:
         resources:
           limits:
             memory: 2G
   ```

**Prevention:**
- Set memory limits in Supervisor config
- Use chunking for large datasets
- Monitor memory usage: `docker stats`

## Elasticsearch Errors

### Issue: Search returns no results

**Symptoms:**
- Product search returns empty array
- Elasticsearch index exists but has no documents
- No errors in logs

**Diagnosis:**
```bash
# Check Elasticsearch index
docker-compose exec elasticsearch curl -X GET "localhost:9200/agencysync/_stats"

# Check document count
docker-compose exec elasticsearch curl -X GET "localhost:9200/agencysync/_count"

# Check Scout indexing
docker-compose exec app php artisan tinker
>>> App\Models\Product::searchable()->count()
```

**Solutions:**

1. **Reindex products**
   ```bash
   docker-compose exec app php artisan scout:import "App\Models\Product"
   ```

2. **Check index mapping**
   ```bash
   docker-compose exec elasticsearch curl -X GET "localhost:9200/agencysync/_mapping?pretty"
   ```

3. **Verify Scout configuration**
   ```bash
   docker-compose exec app php artisan tinker
   >>> config('scout.elasticsearch')
   ```

**Prevention:**
- Index products after sync completion
- Monitor index document count
- Set up sync hooks to update Scout index

### Issue: Elasticsearch cluster health is red/yellow

**Symptoms:**
- Search queries fail
- Dashboard shows cluster error
- Elasticsearch logs show shard failures

**Diagnosis:**
```bash
# Check cluster health
docker-compose exec elasticsearch curl -X GET "localhost:9200/_cluster/health?pretty"

# Check shard allocation
docker-compose exec elasticsearch curl -X GET "localhost:9200/_cat/shards?v"
```

**Solutions:**

1. **Restart Elasticsearch**
   ```bash
   docker-compose restart elasticsearch
   ```

2. **Increase Docker memory limit**
   ```yaml
   # In docker-compose.yml
   services:
     elasticsearch:
       environment:
         - "ES_JAVA_OPTS=-Xms512m -Xmx512m"
   ```

3. **Check disk space**
   ```bash
   df -h  # Elasticsearch needs >15% free space
   ```

**Prevention:**
- Monitor cluster health via dashboard
- Allocate sufficient heap memory (50% of container RAM)
- Maintain adequate disk space

## Database Connection Issues

### Issue: "SQLSTATE[HY000] [2002] Connection refused"

**Symptoms:**
- Application shows database error
- Laravel logs show connection refused
- API returns 500 error

**Diagnosis:**
```bash
# Check MySQL container status
docker-compose ps mysql

# Test MySQL connection
docker-compose exec mysql mysql -u root -p{password} -e "SELECT 1"
```

**Solutions:**

1. **Restart MySQL container**
   ```bash
   docker-compose restart mysql
   ```

2. **Check .env database configuration**
   ```bash
   docker-compose exec app cat /var/www/.env | grep DB_
   ```

3. **Verify network connectivity**
   ```bash
   docker-compose exec app ping mysql
   ```

**Prevention:**
- Use Docker Compose health checks
- Configure automatic restart on failure
- Monitor MySQL error logs

## Slow Performance

### Issue: Dashboard loading slowly (>5 seconds)

**Symptoms:**
- Initial dashboard load takes >5 seconds
- N+1 query problems detected
- High CPU usage on database

**Diagnosis:**
```bash
# Enable query log
docker-compose exec app php artisan tinker
>>> DB::enableQueryLog();

# Check slow queries
docker-compose exec mysql tail -f /var/log/mysql/slow.log
```

**Solutions:**

1. **Enable dashboard caching** (if not already enabled)
   ```bash
   docker-compose exec app php artisan cache:warm
   ```

2. **Add database indexes**
   ```sql
   CREATE INDEX idx_products_tenant_id ON products(tenant_id);
   CREATE INDEX idx_sync_logs_tenant_id ON sync_logs(tenant_id);
   ```

3. **Optimize Eloquent queries**
   ```php
   // Use eager loading
   Product::with('tenant')->get();

   // Select only needed columns
   Product::select(['id', 'name', 'sku'])->get();
   ```

**Prevention:**
- Enable caching for dashboard metrics
- Use query optimization best practices
- Monitor query performance

### Issue: High memory usage

**Symptoms:**
- Docker container using >80% memory limit
- Container restarts due to OOM
- Slow response times

**Diagnosis:**
```bash
# Check container stats
docker stats

# Check PHP memory limit
docker-compose exec app php -i | grep memory_limit
```

**Solutions:**

1. **Increase Docker memory limit**
   ```yaml
   # In docker-compose.yml
   services:
     app:
       deploy:
         resources:
           limits:
             memory: 2G
   ```

2. **Implement chunking** (for large operations)
   ```php
   // Process 1000 records at a time
   Product::chunk(1000, function ($products) {
       // Process products
   });
   ```

3. **Clear Laravel cache**
   ```bash
   docker-compose exec app php artisan cache:clear
   ```

**Prevention:**
- Set appropriate memory limits
- Use chunking for large datasets
- Monitor memory usage regularly

## Cache Issues

### Issue: Stale data shown in dashboard

**Symptoms:**
- Dashboard shows old tenant list
- Metrics don't reflect recent syncs
- Data updates not visible

**Diagnosis:**
```bash
# Check Redis cache
docker-compose exec redis redis-cli KEYS "agency:*"

# Check cache value
docker-compose exec redis redis-cli GET "agency:tenants:list"
```

**Solutions:**

1. **Clear cache**
   ```bash
   docker-compose exec app php artisan cache:clear
   ```

2. **Warm cache manually**
   ```bash
   docker-compose exec app php artisan cache:warm
   ```

3. **Verify event listeners registered**
   ```bash
   docker-compose exec app php artisan tinker
   >>> $listeners = App\Models\Tenant::getEventDispatcher()->getListeners('created');
   >>> print_r($listeners);
   ```

**Prevention:**
- Ensure event listeners registered in AppServiceProvider
- Set appropriate cache TTL (5-15 minutes)
- Monitor cache hit rates

## Getting Additional Help

If issues persist after trying these solutions:

1. **Collect diagnostic information:**
   ```bash
   # Export all logs
   docker-compose logs > diagnostics.log

   # Check container status
   docker-compose ps

   # Check disk space
   df -h

   # Check memory usage
   docker stats --no-stream
   ```

2. **Review error logs:**
   - Laravel: `storage/logs/laravel.log`
   - Nginx: `/var/log/nginx/error.log`
   - Supervisor: `/var/log/supervisor/worker.log`

3. **Consult documentation:**
   - [LOGGING.md](LOGGING.md) — Log locations and viewing
   - [PERFORMANCE.md](PERFORMANCE.md) — Performance optimization
   - Laravel documentation: https://laravel.com/docs/11.x

---

**Related Documentation:**
- [LOGGING.md](LOGGING.md) — Log file locations and formats
- [PERFORMANCE.md](PERFORMANCE.md) — Performance monitoring and optimization

**Last Updated:** 2026-03-14
