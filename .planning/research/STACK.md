# Technology Stack

**Project:** AgencySync
**Researched:** 2026-03-13
**Confidence:** HIGH

## Recommended Stack

### Core Framework

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| PHP | 8.2+ | Runtime | Laravel 11 requires PHP 8.2+. Supports readonly properties, enums, named arguments, and improved type system. |
| Laravel | 11.31+ | Application framework | Already installed. Laravel 11 is LTS (until 2027), minimal structure, improved queue system, native health endpoints. |
| Composer | 2.x | Dependency management | Standard PHP package manager. Already configured. |

### Database

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| MySQL | 8.0+ | Primary database | Native Laravel support, JSON columns for flexible schemas, excellent for multi-tenant via tenant_id approach. |
| Redis | 7.x+ | Cache, queue, sessions | In-memory data store required for Laravel queues, cache, and session driver. |
| Elasticsearch | 8.x+ | Full-text search | Sub-second search performance for large product catalogs. Fuzzy matching, relevance scoring, bulk operations. |
| OpenSearch | 2.x+ | Alternative to Elasticsearch | AWS-compatible alternative if Elasticsearch licensing becomes problematic. |

### Multi-Tenancy

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| stancl/tenancy | ^4.0 | Multi-tenancy package | Most popular Laravel multi-tenancy package. Active development (v9.3.0 released Feb 2026). Supports domain/subdomain/path-based isolation. Automatic tenant resolution in routes. Compatible with Laravel 11. |
| spatie/laravel-multitenancy | NOT RECOMMENDED | Alternative package | Less actively maintained compared to stancl/tenancy. Fewer features for complex scenarios. |

### Search & Indexing

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| laravel/scout | ^10.0 | Search abstraction | Official Laravel package. Provides driver-based search API. Queue-friendly indexing. |
| elastic/elasticsearch-php | ^9.0 | Elasticsearch client | Official PHP client (v9.3.0 current). Compatible with ES 8.x. Async support, proper connection pooling. |
| elastic/scout-driver-plus | ^4.0 | Enhanced Scout driver | Adds advanced query builder, pagination, and aggregation support to Scout. Better than basic Scout driver. |
| witness/elastic-scout-driver | NOT RECOMMENDED | Alternative Scout driver | Less feature-rich than scout-driver-plus. Not actively maintained for ES 8.x. |

### Background Jobs

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| Laravel Queues | Built-in | Job processing | Native queue system in Laravel 11. Redis driver recommended for production. |
| predis/predis | ^2.0 | Redis PHP client | Pure-PHP Redis client. No extension required. Easier deployment in Docker. Alternative: phpredis extension (faster but requires compilation). |
| Laravel Horizon | NOT RECOMMENDED | Queue monitoring | Overkill for single-tenant agency setup. Adds complexity. Use native queue:work with Supervisor instead. |
| supervisor | 3.x+ | Process manager | Keeps queue workers running. Auto-restart on failure. Standard for production Laravel queues. |

### Docker & Infrastructure

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| Docker | 24.x+ | Containerization | Standard container runtime. Required for self-hosted deployment. |
| Docker Compose | 2.x+ | Multi-container orchestration | Already supported by Laravel Sail. Simplifies local development and production deployment. |
| Laravel Sail | ^1.26 | Docker development environment | Already installed. Provides pre-configured Docker setup for Laravel. Easy to extend with Elasticsearch. |
| Nginx | 1.24+ | Reverse proxy | Production web server. Better performance than Apache for Laravel. Included in Sail. |
| PHP-FPM | 8.2+ | PHP process manager | Standard PHP FastCGI manager. Included in Sail. Better performance than mod_php. |

### API & Frontend

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| Laravel API Resources | Built-in | API transformation | Official Laravel package for JSON API responses. Consistent structure. |
| Laravel Sanctum | ^11.0 | API authentication | Lightweight API auth. Included with Laravel 11. Perfect for token-based API auth. |
| Laravel Breeze | NOT RECOMMENDED | Starter kit | Overkill for API-only backend. Use Sanctum directly instead. |
| Tailwind CSS | ^3.4 | Utility-first CSS | Already installed. Perfect for admin dashboard styling. |
| Alpine.js | ^3.x | Lightweight JavaScript | Already installed. Reactive UI without SPA complexity. |

## Installation

```bash
# Multi-tenancy
composer require stancl/tenancy:^4.0
php artisan tenancy:install

# Search & indexing
composer require laravel/scout:^10.0
composer require elastic/elasticsearch-php:^9.0
composer require elastic/scout-driver-plus:^4.0
php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"

# Redis (if not using phpredis extension)
composer require predis/predis:^2.0

# Additional utilities
composer require spatie/laravel-query-builder:^5.0
composer require spatie/laravel-activitylog:^4.0
```

```bash
# Docker services (extend docker-compose.yml)
# Add Elasticsearch and Redis services
```

## Alternatives Considered

| Recommended | Alternative | When to Use Alternative |
|-------------|-------------|-------------------------|
| stancl/tenancy | spatie/laravel-multitenancy | Only if you prefer Spatie's simpler approach, but expect fewer features |
| Elasticsearch | OpenSearch | If deploying to AWS or concerned about Elasticsearch licensing changes |
| Scout Driver Plus | witness/elastic-scout-driver | Only if Scout Driver Plus has compatibility issues, but expect fewer features |
| predis/predis | phpredis extension | If performance is critical and you can compile PHP extensions in Docker |
| Native queues | Laravel Horizon | Only if you need beautiful queue dashboard and have budget for additional complexity |
| MySQL tenant_id | Separate databases per tenant | Only if you need complete data isolation (complexity trade-off) |

## What NOT to Use

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| hyn/multi-tenant | Deprecated, not maintained for Laravel 11 | stancl/tenancy |
| MySQL full-text search | Performance degradation at scale, no fuzzy matching | Elasticsearch |
| Database queue driver | Blocks workers, no retry backoff without additional setup | Redis queue driver |
| Sync queue driver | Blocks HTTP requests, terrible UX | Redis queue driver |
| Laravel Telescope in production | Performance overhead, security risk | Use only in development |
| Elasticsearch 7.x | End of life, security vulnerabilities | Elasticsearch 8.x |
| Scout basic driver | Limited query builder, no aggregations | Scout Driver Plus |
| tinkerwell in production | Security risk, code execution access | Use only in development |

## Stack Patterns by Variant

**If building for AWS deployment:**
- Use OpenSearch instead of Elasticsearch
- Use ElastiCache for Redis instead of self-managed
- Use RDS for MySQL instead of containerized
- Because AWS-native services reduce operational overhead

**If building for self-hosted VPS:**
- Use Docker Compose for all services
- Use Elasticsearch directly (no licensing issues for self-hosted)
- Use containerized Redis
- Because simpler deployment and full control

**If building for development team:**
- Use Laravel Sail for local development
- Use Docker Compose override for Elasticsearch
- Use Predis for Redis (no extension compilation)
- Because zero-setup developer onboarding

**If optimizing for cost:**
- Use single database with tenant_id column
- Use Redis queue with Supervisor
- Use smallest viable Elasticsearch cluster
- Because minimal infrastructure cost while maintaining performance

## Version Compatibility

| Package A | Compatible With | Notes |
|-----------|-----------------|-------|
| laravel/framework 11.31 | stancl/tenancy ^4.0 | Requires Laravel 11.x |
| laravel/scout 10.x | elastic/elasticsearch-php ^9.0 | Requires PHP 8.1+ |
| elastic/scout-driver-plus 4.x | laravel/scout 10.x | Direct dependency on Scout |
| predis/predis 2.x | Redis 7.x | Backward compatible |
| MySQL 8.0+ | Laravel 11.x | Native support, JSON columns |
| Elasticsearch 8.x | elastic/elasticsearch-php ^9.0 | Client supports ES 8.x |

## Development vs Production

**Development (Local):**
- Laravel Sail with Docker Compose
- SQLite for testing (optional)
- Single-node Elasticsearch
- Queue: `php artisan queue:listen --tries=1` (from composer.json)

**Production:**
- Docker Compose or Kubernetes
- MySQL 8.0 with proper indexing
- Redis cluster for queues
- Elasticsearch cluster (3+ nodes for HA)
- Supervisor for queue workers
- Nginx reverse proxy
- PHP-FPM with OPcache

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Core Framework (Laravel 11) | HIGH | Already installed, official documentation confirms |
| Multi-tenancy (stancl/tenancy) | HIGH | Latest release v9.3.0 (Feb 2026), actively maintained |
| Elasticsearch (elastic/elasticsearch-php) | HIGH | Official client, current version v9.3.0 |
| Scout Drivers (scout-driver-plus) | MEDIUM | Popular package but need to verify ES 8.x compatibility in implementation |
| Redis (predis/predis) | HIGH | Standard PHP client, well-documented |
| Docker (Sail, Compose) | HIGH | Already installed, official Laravel tooling |

## Sources

- **Laravel 11 Documentation** — Framework architecture, queues, Scout
- **stancl/tenancy GitHub** — Latest release v9.3.0 (2026-02-04), Laravel 11 compatibility
- **elastic/elasticsearch-php GitHub** — Latest release v9.3.0 (2026-02-04), ES 8.x support
- **Laravel Official Documentation** — Queues, Scout, Docker setup
- **Project composer.json** — Verified installed versions (HIGH confidence)

---
*Stack research for: AgencySync (Laravel 11 multi-tenant backend with Elasticsearch)*
*Researched: 2026-03-13*
