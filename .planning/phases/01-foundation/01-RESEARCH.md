# Phase 1: Foundation & Infrastructure - Research

**Researched:** 2026-03-13
**Domain:** Docker Compose containerization, Laravel 11 infrastructure, service orchestration
**Confidence:** MEDIUM

## Summary

Phase 1 establishes the containerized development environment for AgencySync using Docker Compose v2. The infrastructure includes MySQL 8.0, Elasticsearch 8.x, Redis, Nginx, and PHP-FPM services running in isolated containers with proper environment configuration, network security, and persistent storage. This phase enables developers to start the entire application stack with a single command while following Docker Compose v2 best practices with modern `compose.yaml` format.

**Primary recommendation:** Use Docker Compose v2 with custom service configuration (extending Laravel Sail patterns but with full control), separate networks for frontend/backend services, and a Makefile interface for developer ergonomics.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- **Custom Docker Compose** — Build from scratch with full control over service configuration
- **Compose v2 format** — Use `compose.yaml` (modern syntax) instead of `docker-compose.yml`
- **Hybrid image strategy** — Official Docker images (php:8.2-fpm, mysql:8.0, elasticsearch:8.x) with custom ENTRYPOINT scripts for startup customization
- **Frontend/backend network split** — Separate networks for public-facing (Nginx) and internal services (MySQL, Redis, Elasticsearch) for security best practices
- **Hybrid storage strategy** — Named volumes for database persistence (mysql-data, redis-data, es-data), bind mounts for application code for live reload
- **Single .env file** — All environment variables in one file at project root for simplicity
- **Template approach** — Create `.env.docker` template with Docker-specific variables that developers copy to `.env.local` or add to existing `.env`
- **Always-on containers** — All services run continuously for fast iteration and development
- **Manual startup** — Containers require manual startup (`make up`) for explicit control and resource management
- **Makefile targets** — Developer interaction through Makefile targets (`make up`, `make down`, `make logs`, `make shell`) for portability and self-documentation

### Claude's Discretion
- Specific Elasticsearch version and cluster configuration
- Exact MySQL configuration settings (innodb_buffer_pool, etc.)
- Redis memory and persistence settings
- Nginx worker process configuration
- PHP-FPM pool settings (pm.max_children, etc.)
- Health check implementation details
- Container resource limits (memory, CPU)

### Deferred Ideas (OUT OF SCOPE)
- Production deployment optimization (image caching, multi-stage builds) — Phase 8 (CI/CD)
- Container orchestration (Kubernetes, Swarm) — Out of scope for v1
- Development container hot-reload optimization — Can be added later if needed
- Container monitoring and metrics (Prometheus, Grafana) — Future enhancement
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| INFRA-01 | System uses Docker Compose to run all services | Standard Stack section provides Docker Compose v2 setup with official images |
| INFRA-02 | MySQL 8.0 container for relational database | Standard Stack specifies mysql:8.0 image with named volume persistence |
| INFRA-03 | Elasticsearch container for product search indexing | Standard Stack specifies elasticsearch:8.x image with proper heap settings |
| INFRA-04 | Redis container for queue storage | Standard Stack specifies redis:alpine image with persistence volume |
| INFRA-05 | Nginx container as reverse proxy to PHP-FPM | Standard Stack specifies nginx:alpine with PHP-FPM fastcgi_pass configuration |
| INFRA-06 | Laravel Sail extended with custom services (Elasticsearch, Redis) | Architecture Patterns show custom Docker Compose (not Sail extension) per user decision |
| INFRA-07 | Environment configuration via .env files for all containers | Architecture Patterns show single .env file approach with per-service env var declaration |
| INFRA-08 | System can start with single command (docker-compose up) | Makefile interface section provides `make up` target wrapping docker compose up |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Docker Compose | v2 (compose.yaml) | Service orchestration | Modern Compose v2 syntax is the current standard (replaces docker-compose.yml v1 format) |
| MySQL | 8.0 | Relational database | Official mysql:8.0 image, stable, Laravel default, full UTF-8 MB4 support |
| Elasticsearch | 8.x | Full-text search indexing | Elastic's official Docker image, required for sub-second search performance requirement |
| Redis | 7.x alpine | Queue storage | Official redis:alpine image, lightweight, Laravel queue default |
| Nginx | 1.x alpine | Reverse proxy web server | Official nginx:alpine image, serves static assets, proxies PHP to PHP-FPM |
| PHP | 8.2-fpm | Application runtime | Official php:8.2-fpm image, Laravel 11 requirement (PHP ^8.2) |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Docker Compose Switch (compose-switch) | Latest | V1 to V2 compatibility | If developers have existing docker-compose.yml workflows (transition tool) |
| Make | Any (GNU Make 4.x) | Developer command interface | Provides portable, self-documenting command interface across OS |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Custom Docker Compose | Laravel Sail | Sail provides convenience but loses fine-grained control over service config; user chose custom for full control |
| Docker Compose v2 | Docker Swarm | Swarm adds orchestration complexity unnecessary for single-host development; Compose v2 sufficient |
| Named volumes | Bind mounts for all data | Bind mounts for database data causes permission issues and performance degradation on macOS/Windows; named volumes are best practice for databases |
| Single .env file | Per-service .env files | Multiple .env files add complexity; single file with explicit per-service env var declaration is simpler and error-resistant |

**Installation:**
```bash
# No package installation needed - uses Docker images
# Developers only need Docker Engine 24+ (includes Docker Compose v2 plugin)
# Verify: docker compose version
```

## Architecture Patterns

### Recommended Project Structure
```
agency-sync/
├── compose.yaml              # Docker Compose v2 configuration (main file)
├── Dockerfile                # PHP-FPM custom image build
├── Makefile                  # Developer command interface
├── .env.example              # Template for environment variables
├── .env.docker               # Docker-specific environment variables template
├── docker/
│   ├── nginx/
│   │   └── default.conf      # Nginx virtual host configuration
│   ├── php/
│   │   ├── Dockerfile        # PHP-FPM custom image (if separate from root)
│   │   └── php.ini           # Custom PHP configuration overrides
│   └── elasticsearch/
│       └── elasticsearch.yml # Elasticsearch cluster configuration
├── storage/                  # Laravel storage (bind-mounted into app container)
└── bootstrap/cache/          # Laravel cache (bind-mounted into app container)
```

### Pattern 1: Docker Compose v2 Service Definition
**What:** Modern Compose v2 uses `compose.yaml` (not `docker-compose.yml`) with improved syntax and is now integrated as a Docker plugin (`docker compose` instead of `docker-compose`).

**When to use:** All new Docker Compose projects in 2026; v1 format is deprecated.

**Example:**
```yaml
# compose.yaml (v2 format)
services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: agency-sync-app
    restart: unless-stopped
    working_dir: /var/www
    volumes:
      - ./:/var/www
      - /var/www/vendor  # Prevent vendor mount issues
    environment:
      - APP_ENV=${APP_ENV:-local}
      - DB_CONNECTION=mysql
      - DB_HOST=mysql
      - DB_PORT=3306
      - DB_DATABASE=${DB_DATABASE:-agencysync}
      - DB_USERNAME=${DB_USERNAME:-agencysync}
      - DB_PASSWORD=${DB_PASSWORD:-secret}
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - ELASTICSEARCH_HOST=elasticsearch
      - ELASTICSEARCH_PORT=9200
    networks:
      - backend
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy
      elasticsearch:
        condition: service_healthy

  nginx:
    image: nginx:1.25-alpine
    container_name: agency-sync-nginx
    restart: unless-stopped
    ports:
      - "${NGINX_PORT:-80}:80"
    volumes:
      - ./:/var/www
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    networks:
      - frontend
      - backend
    depends_on:
      - app

  mysql:
    image: mysql:8.0
    container_name: agency-sync-mysql
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD=${DB_ROOT_PASSWORD:-root}
      MYSQL_DATABASE=${DB_DATABASE:-agencysync}
      MYSQL_USER=${DB_USERNAME:-agencysync}
      MYSQL_PASSWORD=${DB_PASSWORD:-secret}
    volumes:
      - mysql-data:/var/lib/mysql
    networks:
      - backend
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5

  redis:
    image: redis:7-alpine
    container_name: agency-sync-redis
    restart: unless-stopped
    command: redis-server --appendonly yes
    volumes:
      - redis-data:/data
    networks:
      - backend
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5

  elasticsearch:
    image: elasticsearch:8.13.0
    container_name: agency-sync-elasticsearch
    restart: unless-stopped
    environment:
      - discovery.type=single-node
      - xpack.security.enabled=false
      - "ES_JAVA_OPTS=-Xms512m -Xmx512m"
      - bootstrap.memory_lock=true
    ulimits:
      memlock:
        soft: -1
        hard: -1
    volumes:
      - es-data:/usr/share/elasticsearch/data
    networks:
      - backend
    healthcheck:
      test: ["CMD-SHELL", "curl -sf http://localhost:9200/_cluster/health || exit 1"]
      interval: 30s
      timeout: 10s
      retries: 5

networks:
  frontend:
    driver: bridge
  backend:
    driver: bridge
    internal: true  # Isolate from external network for security

volumes:
  mysql-data:
    driver: local
  redis-data:
    driver: local
  es-data:
    driver: local
```

### Pattern 2: PHP-FPM Dockerfile for Laravel 11
**What:** Custom PHP-FPM image with Laravel-specific extensions and Composer pre-installed.

**When to use:** Building the application container that runs Laravel 11 code.

**Example:**
```dockerfile
# Dockerfile
FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libicu-dev \
    libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions required for Laravel 11
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    intl \
    zip

# Install Redis extension for queue support
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer (copy from official Composer image)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy application files
COPY . /var/www

# Set permissions for Laravel directories
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage \
    && chmod -R 775 /var/www/bootstrap/cache

# Copy startup script
COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
```

### Pattern 3: Nginx Virtual Host Configuration
**What:** Nginx reverse proxy configuration that forwards PHP requests to PHP-FPM container and serves static assets directly.

**When to use:** Configuring the Nginx service in Docker Compose.

**Example:**
```nginx
# docker/nginx/default.conf
server {
    listen 80;
    server_name localhost;
    root /var/www/public;

    index index.php index.html;

    # Log files
    access_log /var/log/nginx/access.log;
    error_log /var/log/nginx/error.log;

    # Handle PHP requests via PHP-FPM
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass app:9000;  # 'app' is the service name in compose.yaml
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
    }

    # Handle Laravel routes
    location / {
        try_files $uri $uri/ /index.php?$query_string;
        gzip_static on;
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }
}
```

### Pattern 4: Makefile Developer Interface
**What:** Makefile provides portable, self-documenting commands for common Docker operations, abstracting away docker compose syntax.

**When to use:** All developer interactions with the Docker environment.

**Example:**
```makefile
# Makefile
.PHONY: up down logs shell shell-nginx shell-mysql shell-redis shell-es install migrate seed test

# Default target
.DEFAULT_GOAL := help

# Start all containers
up:
	@echo "Starting containers..."
	docker compose up -d
	@echo "Containers started. Access app at http://localhost"

# Stop all containers
down:
	@echo "Stopping containers..."
	docker compose down
	@echo "Containers stopped."

# View logs from all containers
logs:
	docker compose logs -f

# Shell into app container
shell:
	docker compose exec app bash

# Shell into nginx container
shell-nginx:
	docker compose exec nginx sh

# Shell into mysql container
shell-mysql:
	docker compose exec mysql mysql -u$(DB_USERNAME) -p$(DB_PASSWORD) $(DB_DATABASE)

# Run composer install
install:
	docker compose exec app composer install

# Run database migrations
migrate:
	docker compose exec app php artisan migrate

# Seed database
seed:
	docker compose exec app php artisan db:seed

# Run tests
test:
	docker compose exec app php artisan test

# Build and start containers (first time setup)
build:
	@echo "Building containers..."
	docker compose build
	@echo "Installing dependencies..."
	docker compose run --rm app composer install
	@echo "Copying .env.example to .env..."
	@docker compose exec -T app sh -c 'test -f .env || cp .env.example .env'
	@echo "Generating application key..."
	docker compose exec app php artisan key:generate
	@echo "Running migrations..."
	docker compose exec app php artisan migrate
	@echo "Setup complete!"
```

### Anti-Patterns to Avoid
- **Bind-mounting vendor/ directory:** Causes performance issues and permission errors on macOS/Windows. Use `/var/www/vendor` anonymous volume to prevent bind mount.
- **Using `docker-compose.yml` (v1 format):** Deprecated syntax; use `compose.yaml` with `docker compose` (v2) command.
- **Exposing database ports to host:** Security risk; keep MySQL, Redis, Elasticsearch on internal network only.
- **Using `latest` tag for images:** Unpredictable updates can break production; pin specific versions (e.g., `mysql:8.0` not `mysql:latest`).
- **Not setting health checks:** Containers may start before dependencies are ready, causing startup failures; use healthcheck and `depends_on: {condition: service_healthy}`.
- **Hardcoding environment variables in compose.yaml:** Makes configuration rigid; use `${VAR:-default}` syntax for flexibility.
- **Using root user in containers:** Security risk; Laravel containers should run as `www-data` user where possible.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| PHP extensions installation | Manual compilation and installation | Official `php:*-fpm` base images with `docker-php-ext-install` | Handles dependency resolution, PECL integration, and PHP version compatibility |
| Package management | Custom npm/yarn scripts in containers | Official `composer:latest` and `node:*` images | Pre-configured with proper tools, permissions, and caching |
| Process supervision | Custom init scripts | Docker's built-in restart policies (`restart: unless-stopped`) | Handles process monitoring, automatic restarts, and crash recovery |
| Service discovery | Hardcoded container IPs | Docker's embedded DNS service | Containers resolve each other by service name, automatic updates on scale changes |
| Volume management | Manual directory creation and permissions | Docker named volumes (`mysql-data:`, etc.) | Cross-platform compatibility, automatic initialization, proper ownership |
| Log aggregation | Custom log parsing scripts | `docker compose logs -f` with per-service log files | Built-in log aggregation, color-coded output, timestamp support |
| SSL/TLS termination | Custom nginx SSL config | Separate reverse proxy container (traefik/nginx-proxy) | Automated certificate management (Let's Encrypt), renewal handling |

**Key insight:** Docker Compose provides production-grade primitives for orchestration, networking, storage, and service discovery. Building custom solutions for these problems reinvents functionality that's already battle-tested and maintained by the Docker community.

## Common Pitfalls

### Pitfall 1: Container Startup Race Conditions
**What goes wrong:** Application container starts and tries to connect to MySQL before database is fully initialized, causing "Connection refused" errors on first boot.

**Why it happens:** Docker Compose `depends_on` only waits for containers to start, not for applications inside to be ready.

**How to avoid:** Use healthcheck on dependency services and `depends_on: {condition: service_healthy}` in dependent services.

**Warning signs:** Random startup failures, "SQLSTATE[HY000] [2002] Connection refused" in logs, need to restart containers manually.

**Example fix:**
```yaml
services:
  mysql:
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5

  app:
    depends_on:
      mysql:
        condition: service_healthy
```

### Pitfall 2: File Permission Issues on macOS/Windows
**What goes wrong:** Laravel fails to write to `storage/` or `bootstrap/cache/` directories with "Permission denied" errors, even after chmod.

**Why it happens:** Docker on macOS/Windows uses a VM to run Linux containers; bind mounts can create files owned by root or mismatched UID/GID between host and container.

**How to avoid:** Set `user` directive in Dockerfile to run as `www-data`, ensure storage directories exist with correct permissions before bind mount, use named volumes for data that doesn't need live editing.

**Warning signs:** "Failed to open stream: Permission denied" errors, logs directory unwritable, cache failures.

### Pitfall 3: Elasticsearch Heap Memory Issues
**What goes wrong:** Elasticsearch container crashes immediately or fails to start with "Java heap space" errors.

**Why it happens:** Elasticsearch requires at least 50% of available RAM for heap but defaults to 1GB; containers often have limited memory, causing OOM kills.

**How to avoid:** Set `ES_JAVA_OPTS=-Xms512m -Xmx512m` to explicitly control heap size (never exceed 50% of container memory limit), disable swapping with `bootstrap.memory_lock=true`.

**Warning signs:** Container exits with code 137, "OutOfMemoryError" in Elasticsearch logs, slow search performance.

### Pitfall 4: Vendor Directory Mount Issues
**What goes wrong:** Composer dependencies disappear or get corrupted, autoloader breaks, "Class not found" errors.

**Why it happens:** Bind-mounting entire project overwrites `/var/www/vendor` directory from host (where it doesn't exist or is empty), causing PHP to not find installed packages.

**How to avoid:** Add anonymous volume `/var/www/vendor` in compose.yaml to prevent bind mount of vendor directory.

**Warning signs:** "Class 'Facade\Ignition\Ignition' not found" (Laravel debugging), "Class 'Composer\Autoload\ClassLoader' not found", require/include failures.

**Example fix:**
```yaml
services:
  app:
    volumes:
      - ./:/var/www
      - /var/www/vendor  # Prevent bind mount over vendor
```

### Pitfall 5: Environment Variable Scope Confusion
**What goes wrong:** Container can't access environment variables defined in `.env` file, or all host variables leak into container.

**Why it happens:** Docker Compose automatically reads `.env` file but doesn't pass all vars to containers; explicit `environment:` declaration required per service.

**How to avoid:** Explicitly declare which env vars each container receives using `${VAR:-default}` syntax; don't rely on automatic pass-through.

**Warning signs:** "undefined environment variable" errors, containers using wrong config values, `.env` changes not taking effect.

## Code Examples

Verified patterns from official sources:

### Docker Compose Health Check with Dependencies
```yaml
# Source: Docker Compose file specification v3.8
# https://docs.docker.com/compose/compose-file/compose-file-v3/#healthcheck
services:
  mysql:
    image: mysql:8.0
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 30s

  app:
    image: php:8.2-fpm
    depends_on:
      mysql:
        condition: service_healthy
```

### Elasticsearch Single-Node Cluster Configuration
```yaml
# Source: Elasticsearch Docker documentation
# https://www.elastic.co/guide/en/elasticsearch/reference/current/docker.html
services:
  elasticsearch:
    image: elasticsearch:8.13.0
    environment:
      - discovery.type=single-node
      - xpack.security.enabled=false
      - "ES_JAVA_OPTS=-Xms512m -Xmx512m"
    ulimits:
      memlock:
        soft: -1
        hard: -1
    volumes:
      - es-data:/usr/share/elasticsearch/data
```

### Nginx PHP-FPM Proxy Configuration
```nginx
# Source: Nginx PHP-FPM documentation
# https://www.nginx.com/resources/wiki/start/topics/examples/phpfcgi/
location ~ \.php$ {
    try_files $uri =404;
    fastcgi_split_path_info ^(.+\.php)(/.+)$;
    fastcgi_pass app:9000;
    fastcgi_index index.php;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_param PATH_INFO $fastcgi_path_info;
}
```

### Environment Variable Substitution
```yaml
# Source: Docker Compose environment variable specification
# https://docs.docker.com/compose/environment-variables/
services:
  app:
    environment:
      - APP_ENV=${APP_ENV:-local}  # Default to 'local' if not set
      - DB_HOST=mysql
      - DB_DATABASE=${DB_DATABASE:-agencysync}
      - DB_PASSWORD=${DB_PASSWORD:-secret}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| docker-compose.yml (v1) | compose.yaml (v2) | 2022-2023 | Docker Compose is now a Docker plugin (`docker compose` not `docker-compose`), new syntax and features |
| docker-compose (standalone CLI) | docker compose (plugin) | Docker Desktop 4.0+ | Plugin is integrated with Docker Engine, faster updates, unified CLI |
| Link containers (--link) | Docker networks (bridge driver) | Docker 1.10+ (2016) | Networks provide automatic DNS discovery, better isolation, service name resolution |
| Fig (Docker Compose predecessor) | Docker Compose v1 | 2014 | Docker acquired Fig, became Docker Compose; Fig is now obsolete |
| Environment variables in docker-compose.yml | Substitution from .env file | Docker Compose 1.7+ | Cleaner separation of config from code, easier env-specific overrides |

**Deprecated/outdated:**
- **docker-compose.yml (v1 format):** Deprecated in favor of compose.yaml with Docker Compose v2 plugin syntax
- **--link flag:** Replaced by user-defined networks with automatic service discovery via DNS
- **fig:** Original tool that became Docker Compose; completely obsolete, use docker compose command
- **Docker Compose standalone binary:** Replaced by Docker Compose plugin included with Docker Engine
- **container_name for service discovery:** No longer needed; services resolve by service name automatically on shared network

## Open Questions

1. **Elasticsearch version pinning**
   - What we know: Elasticsearch 8.x is current major version, specific patch version should be pinned
   - What's unclear: Which specific 8.x version (8.13.0, 8.12.2, etc.) is most stable for Laravel Scout integration
   - Recommendation: Pin to 8.13.0 (current stable as of early 2026), validate during Phase 5 integration testing

2. **MySQL configuration tuning**
   - What we know: MySQL 8.0 requires proper innodb_buffer_pool_size for performance
   - What's unclear: Optimal settings for development environment (production tuning deferred to Phase 8)
   - Recommendation: Use MySQL 8.0 defaults for Phase 1 (sufficient for development), document production tuning for Phase 8

3. **PHP-FPM pool configuration**
   - What we know: PHP-FPM requires pm.max_children, pm.start_servers, pm.min_spare_servers, pm.max_spare_servers settings
   - What's unclear: Optimal values for single-developer environment vs production workload
   - Recommendation: Use default dynamic settings for Phase 1 (pm = dynamic), tune based on load testing in Phase 8

4. **Container resource limits**
   - What we know: Docker supports mem_limit and cpus constraints in compose.yaml
   - What's unclear: Whether to enforce limits in development (can cause issues on low-resource machines)
   - Recommendation: Document recommended limits but don't enforce by default; provide commented examples for developers to enable

5. **Volume backup/restore strategy**
   - What we know: Named volumes persist container restarts but require special commands to backup
   - What we know: Need strategy for developers to backup database and Elasticsearch data
   - What's unclear: Whether to include backup scripts in Makefile or defer to developer discretion
   - Recommendation: Add `make db-backup` and `make db-restore` targets using `docker compose exec` for MySQL dumps; document ES snapshot API for Elasticsearch

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 11.x |
| Config file | `phpunit.xml` (exists in project root) |
| Quick run command | `docker compose exec app php artisan test --parallel` |
| Full suite command | `docker compose exec app php artisan test` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| INFRA-01 | Docker Compose starts all services | smoke | `docker compose up -d && timeout 30 docker compose ps` | ❌ Wave 0 |
| INFRA-02 | MySQL container accessible from app container | integration | `docker compose exec app php artisan migrate:status` | ❌ Wave 0 |
| INFRA-03 | Elasticsearch container accepts HTTP requests | integration | `docker compose exec elasticsearch curl -sf http://localhost:9200/_cluster/health` | ❌ Wave 0 |
| INFRA-04 | Redis container accepts connections | integration | `docker compose exec redis redis-cli ping` | ❌ Wave 0 |
| INFRA-05 | Nginx serves Laravel index.php | smoke | `curl -sf http://localhost/ | grep -q "Laravel"` | ❌ Wave 0 |
| INFRA-06 | All services defined in compose.yaml | lint | `docker compose config > /dev/null && echo "Valid"` | ❌ Wave 0 |
| INFRA-07 | Environment variables accessible in containers | integration | `docker compose exec app php artisan ttd -- env | grep DB_HOST` | ❌ Wave 0 |
| INFRA-08 | Single command starts entire stack | smoke | `make up && docker compose ps` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `docker compose ps` (verify all services running)
- **Per wave merge:** `make up && docker compose exec app php artisan test` (full integration smoke test)
- **Phase gate:** All INFRA tests pass before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Infrastructure/DockerComposeTest.php` — smoke tests for INFRA-01, INFRA-06, INFRA-08
- [ ] `tests/Integration/DatabaseConnectionTest.php` — integration test for INFRA-02
- [ ] `tests/Integration/ElasticsearchConnectionTest.php` — integration test for INFRA-03
- [ ] `tests/Integration/RedisConnectionTest.php` — integration test for INFRA-04
- [ ] `tests/Integration/NginxProxyTest.php` — smoke test for INFRA-05
- [ ] `tests/Integration/EnvironmentConfigTest.php` — integration test for INFRA-07
- [ ] `Makefile` — create `make up`, `make down`, `make logs`, `make test` targets
- [ ] `compose.yaml` — create Docker Compose v2 configuration
- [ ] `docker/nginx/default.conf` — create Nginx virtual host configuration
- [ ] `Dockerfile` — create PHP-FPM custom image
- [ ] `.env.docker` — create Docker-specific environment variables template
- [ ] Framework install: PHPUnit 11.x already installed in composer.json ✓

## Sources

### Primary (HIGH confidence)
- Docker Compose File Specification v3.8 - https://docs.docker.com/compose/compose-file/compose-file-v3/ (verified syntax, healthchecks, depends_on conditions)
- Elasticsearch Docker Documentation - https://www.elastic.co/guide/en/elasticsearch/reference/current/docker.html (verified heap settings, discovery.type, ulimits)
- Nginx PHP-FPM Configuration - https://www.nginx.com/resources/wiki/start/topics/examples/phpfcgi/ (verified fastcgi_pass configuration)
- Docker Environment Variables - https://docs.docker.com/compose/environment-variables/ (verified substitution syntax, .env file behavior)
- Docker Storage Volumes - https://docs.docker.com/storage/volumes/ (verified named volumes vs bind mounts)

### Secondary (MEDIUM confidence)
- Laravel 11 Documentation - https://laravel.com/docs/11.x/deployment#docker (verified Laravel Docker requirements, PHP 8.2+)
- PHP-FPM Configuration - https://www.php.net/manual/en/install.fpm.configuration.php (verified pm settings, pool configuration)
- Docker Compose v2 Migration Guide - https://docs.docker.com/compose/migrate/ (verified v1 to v2 differences, command changes)

### Tertiary (LOW confidence)
- MySQL Docker Image Documentation - https://hub.docker.com/_/mysql (verified image tags, environment variables, not verified for 8.0-specific configuration)
- Redis Docker Image Documentation - https://hub.docker.com/_/redis (verified image tags, persistence commands, not verified for 7.x-specific features)
- Docker Healthcheck Documentation - https://docs.docker.com/engine/reference/builder/#healthcheck (verified syntax, not verified for all service-specific healthcheck commands)

## Metadata

**Confidence breakdown:**
- Standard stack: MEDIUM - Based on official Docker image documentation (mysql:8.0, elasticsearch:8.x, redis:7-alpine, nginx:alpine, php:8.2-fpm), but specific versions require validation
- Architecture: HIGH - Docker Compose v2 patterns, healthchecks, networks, volumes are well-documented and stable
- Pitfalls: HIGH - Race conditions, permissions, vendor mounts are well-documented in Laravel + Docker communities
- Code examples: HIGH - Verified against official Docker, Nginx, Elasticsearch documentation

**Research date:** 2026-03-13
**Valid until:** 2026-04-13 (30 days - Docker ecosystem is stable, but verify Elasticsearch version before Phase 5)
