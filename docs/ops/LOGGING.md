# AgencySync Logging

This document covers log file locations, viewing commands, and log formats for all AgencySync services.

## Log File Locations

### Laravel Application Logs

**Location:** `storage/logs/laravel.log`

**Container path:** `/var/www/storage/logs/laravel.log`

**Contains:**
- Application errors and exceptions
- HTTP request logs (if enabled)
- Queue job logs
- Custom log entries

**View logs:**
```bash
# Via Makefile
make logs

# Via Docker Compose
docker-compose logs -f app

# Direct file access
docker-compose exec app tail -f storage/logs/laravel.log

# Last 100 lines
docker-compose exec app tail -n 100 storage/logs/laravel.log
```

**Log levels:** Emergency, Alert, Critical, Error, Warning, Notice, Info, Debug

**Example log entry:**
```
[2026-03-14 10:15:30] local.ERROR: SyncProductsJob failed: {"exception":"[object] (Exception(code: 0): Shopify API rate limit exceeded at /var/www/app/Jobs/SyncProductsJob.php:45)
[stacktrace]
#0 /var/www/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(115): App\\Jobs\\SyncProductsJob->handle()
...
"}
```

### Nginx Access Logs

**Location:** `/var/log/nginx/access.log` (inside nginx container)

**Contains:**
- HTTP requests to web server
- Response status codes
- Request duration
- Client IP addresses

**View logs:**
```bash
# Via Docker Compose
docker-compose logs -f nginx

# Direct file access
docker-compose exec nginx tail -f /var/log/nginx/access.log

# Last 50 lines
docker-compose exec nginx tail -n 50 /var/log/nginx/access.log
```

**Example log entry:**
```
172.18.0.1 - - [14/Mar/2026:10:15:30 +0000] "GET /api/v1/tenants HTTP/1.1" 200 1234 "-" "Mozilla/5.0"
```

**Format:** `$remote_addr - $remote_user [$time_local] "$request" $status $body_bytes_sent "$http_referer" "$http_user_agent"`

### Nginx Error Logs

**Location:** `/var/log/nginx/error.log` (inside nginx container)

**Contains:**
- Nginx configuration errors
- Upstream connection failures
- PHP-FPM errors
- SSL/TLS errors

**View logs:**
```bash
# Via Docker Compose
docker-compose logs -f nginx 2>&1 | grep error

# Direct file access
docker-compose exec nginx tail -f /var/log/nginx/error.log
```

**Example log entry:**
```
2026/03/14 10:15:30 [error] 32#32: *1 upstream timed out (110: Connection timed out) while reading response header from upstream, client: 172.18.0.1, server: _, request: "GET /api/v1/tenants HTTP/1.1"
```

### Supervisor Logs (Queue Workers)

**Location:** `/var/log/supervisor/` (inside app container)

**Files:**
- `worker.log` — Queue worker output
- `supervisord.log` — Supervisor daemon logs

**Contains:**
- Queue worker start/stop events
- Job execution logs
- Worker crash/restart logs
- stderr output from jobs

**View logs:**
```bash
# Via Docker Compose
docker-compose logs -f supervisor

# Direct file access
docker-compose exec app tail -f /var/log/supervisor/worker.log

# Supervisor daemon logs
docker-compose exec app tail -f /var/log/supervisor/supervisord.log
```

**Example log entry (worker.log):**
```
[2026-03-14 10:15:30] Processing: App\\Jobs\\SyncProductsJob
[2026-03-14 10:15:35] Processed:  App\\Jobs\\SyncProductsJob (5.23s)
```

**Example log entry (supervisord.log):**
```
2026-03-14 10:15:30,340 CRIT Supervisor running as root (user 0)
2026-03-14 10:15:30,341 INFO RPC interface 'supervisor' initialized
```

### MySQL Logs

**Location:** `/var/log/mysql/error.log` (inside mysql container)

**Contains:**
- Database errors
- Slow query log (if enabled)
- Connection errors
- Replication issues (not used in v1)

**View logs:**
```bash
# Via Docker Compose
docker-compose logs -f mysql

# Direct file access
docker-compose exec mysql tail -f /var/log/mysql/error.log
```

**Example log entry:**
```
2026-03-14T10:15:30.123456Z 0 [Note] Access denied for user 'root'@'localhost'
```

### Elasticsearch Logs

**Location:** `/var/log/elasticsearch/` (inside elasticsearch container)

**Contains:**
- Cluster health
- Index operations
- Search errors
- JVM memory usage

**View logs:**
```bash
# Via Docker Compose
docker-compose logs -f elasticsearch

# Direct file access
docker-compose exec elasticsearch tail -f /var/log/elasticsearch/agencysync.log
```

**Example log entry:**
```
[2026-03-14T10:15:30,123][INFO ][o.e.c.m.MetaDataCreateIndexService] [agencysync] creating index
```

### Redis Logs

**Location:** (stdout/stderr, redirected to Docker logs)

**Contains:**
- Redis startup logs
- Connection events
- Persistence (RDB/AOF) logs
- Memory warnings

**View logs:**
```bash
# Via Docker Compose
docker-compose logs -f redis
```

**Example log entry:**
```
1:M 14 Mar 2026 10:15:30.123 * DB saved on disk
```

## Log Rotation

### Laravel Logs

Laravel uses daily log rotation by default.

**Location:** `storage/logs/laravel-{YYYY-MM-DD}.log`

**Retention:** Configured in `config/app.php` (default: infinite)

**Manual cleanup:**
```bash
# Remove logs older than 30 days
docker-compose exec app find storage/logs -name "laravel-*.log" -mtime +30 -delete
```

### Docker Container Logs

Docker Compose manages container logs.

**View rotation config:**
```bash
docker-compose ps  # Shows log driver config
```

**Default limits:** None (logs grow indefinitely)

**Add rotation to docker-compose.yml:**
```yaml
services:
  app:
    logging:
      driver: "json-file"
      options:
        max-size: "10m"
        max-file: "3"
```

### Nginx Logs

Nginx logs rotate via logrotate.

**Configuration:** `/etc/logrotate.d/nginx`

**Default schedule:** Daily, keep 14 days

## Viewing Tips

### Filter by log level
```bash
# Laravel errors only
docker-compose exec app grep "ERROR" storage/logs/laravel.log

# Laravel warnings and errors
docker-compose exec app grep -E "ERROR|WARNING" storage/logs/laravel.log
```

### Filter by time
```bash
# Last hour of logs
docker-compose exec app tail -n 1000 storage/logs/laravel.log | grep "2026-03-14 1[0-2]:"

# Real-time monitoring
docker-compose logs -f --tail=100 app
```

### Search for specific terms
```bash
# Search for tenant ID
docker-compose logs app | grep "tenant-uuid-123"

# Search for API errors
docker-compose logs nginx | grep " 500 "
```

### Export logs for analysis
```bash
# Copy Laravel logs to host
docker-compose exec app cat storage/logs/laravel.log > laravel.log

# Copy all container logs
docker-compose logs > all-logs.txt
```

## Log Analysis Tools

**Basic statistics:**
```bash
# Count error occurrences
docker-compose exec app grep -c "ERROR" storage/logs/laravel.log

# Top 10 HTTP status codes
docker-compose exec nginx awk '{print $9}' /var/log/nginx/access.log | sort | uniq -c | sort -rn | head -n 10
```

**Real-time dashboard (install glances):**
```bash
pip install glances
docker-compose exec app glances
```

## Troubleshooting

See [TROUBLESHOOTING.md](TROUBLESHOOTING.md) for common issues and solutions.

---

**Related Documentation:**
- [TROUBLESHOOTING.md](TROUBLESHOOTING.md) — Common errors and solutions
- [PERFORMANCE.md](PERFORMANCE.md) — Performance monitoring and optimization

**Last Updated:** 2026-03-14
