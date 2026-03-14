# AgencySync Operations Documentation

This directory contains operational documentation for maintaining, monitoring, and troubleshooting AgencySync deployments.

## Documentation

### [LOGGING.md](LOGGING.md)
Log file locations, viewing commands, and log formats for all services (Nginx, Laravel, Supervisor).

**Topics:**
- Log file locations (Docker containers and host paths)
- Viewing logs via Docker Compose, Makefile, and direct access
- Log formats and examples
- Log rotation and retention policies

### [TROUBLESHOOTING.md](TROUBLESHOOTING.md)
Common errors, diagnostic steps, and solutions for production issues.

**Topics:**
- Sync operation failures
- Queue worker issues
- Elasticsearch errors
- Database connection problems
- Slow performance
- Cache issues

### [PERFORMANCE.md](PERFORMANCE.md)
Performance monitoring, cache optimization, and query tuning guidance.

**Topics:**
- Cache monitoring (Redis)
- Slow query detection (MySQL)
- Elasticsearch performance
- Queue worker optimization
- Dashboard caching strategies

## Quick Reference

### View All Logs
```bash
make logs
# or
docker-compose logs -f
```

### View Specific Service Logs
```bash
docker-compose logs -f nginx      # Nginx access/error logs
docker-compose logs -f app        # Laravel logs
docker-compose logs -f supervisor # Queue worker logs
```

### Access Container Shell
```bash
make app
# or
docker-compose exec app bash
```

### Restart Services
```bash
docker-compose restart nginx      # Restart Nginx
docker-compose restart supervisor # Restart queue workers
```

### Clear Laravel Cache
```bash
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan route:clear
docker-compose exec app php artisan view:clear
```

## Common Commands

### Queue Management
```bash
# Check queue status
docker-compose exec app php artisan queue:failed

# Restart queue workers
docker-compose restart supervisor

# Clear failed jobs
docker-compose exec app php artisan queue:flush
```

### Cache Management
```bash
# Warm caches (after deployment)
docker-compose exec app php artisan cache:warm

# Clear all caches
docker-compose exec app php artisan cache:clear

# View Redis cache keys
docker-compose exec redis redis-cli KEYS "agency:*"
```

### Database Operations
```bash
# Run migrations
docker-compose exec app php artisan migrate

# Create database backup
docker-compose exec mysql mysqldump -u root -p{password} agencysync > backup.sql

# Access MySQL shell
docker-compose exec mysql mysql -u root -p{password} agencysync
```

## Getting Help

For issues not covered in this documentation:
1. Check Laravel documentation: https://laravel.com/docs/11.x
2. Check Docker Compose documentation: https://docs.docker.com/compose/
3. Review error logs in LOGGING.md locations
4. Search TROUBLESHOOTING.md for similar issues

## Maintenance Schedule

### Daily
- Monitor disk space usage (`df -h`)
- Check queue failed jobs table
- Review error logs for critical issues

### Weekly
- Review export file retention (storage/app/exports/)
- Check Elasticsearch disk usage
- Monitor Redis memory usage

### Monthly
- Review and rotate logs if needed
- Update dependencies (composer, npm)
- Review cache hit rates and optimize

---

**Last Updated:** 2026-03-14
**AgencySync Version:** 1.0.0
