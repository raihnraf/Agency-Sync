# Phase 8: CI/CD & Testing - Research

**Researched:** 2026-03-14
**Domain:** GitHub Actions CI/CD, PHPUnit Testing, Docker Deployment, Code Coverage
**Confidence:** HIGH

## Summary

This phase implements automated CI/CD pipeline with GitHub Actions and comprehensive test coverage for AgencySync. The project already has strong test infrastructure (88 test files, PHPUnit 11.x, Laravel 11.48) with SQLite in-memory database testing. The deployment target is a Docker Compose-based self-hosted server running MySQL, Redis, Elasticsearch, and Nginx.

**Primary recommendations:**
1. Use GitHub Actions with `shivammathur/setup-php@v2` for PHP 8.2/8.3 and standard Laravel testing workflow
2. Configure PHPUnit coverage with Xdebug using `<coverage>` section in phpunit.xml
3. Deploy via SSH using `appleboy/ssh-action` with git pull + Docker Compose restart strategy
4. Zero-downtime deployment not required for v1 (single-user agency tool)
5. Test coverage enforcement via CI pipeline with 70% minimum threshold

## Standard Stack

### Core CI/CD Tools
| Tool | Version | Purpose | Why Standard |
|------|---------|---------|--------------|
| GitHub Actions | - | CI/CD orchestration | Native GitHub integration, free for public repos, 2000 minutes/month private |
| PHPUnit | 11.0.1+ | Test framework | Laravel 11 default, feature-rich, mature ecosystem |
| Xdebug | 3.3+ | Code coverage | Standard PHP coverage tool, integrates with PHPUnit |
| shivammathur/setup-php | v2 | PHP environment for Actions | Official PHP setup action, supports extensions, coverage tools |

### Deployment Tools
| Tool | Version | Purpose | Why Standard |
|------|---------|---------|--------------|
| appleboy/ssh-action | v1.0.0+ | Remote execution | Battle-tested, 8K+ stars, handles SSH keys gracefully |
| Docker Compose | v2+ | Container orchestration | Project already uses Compose, declarative deployments |
| git | - | Code deployment | Simple, reliable, standard for self-hosted apps |

### Supporting Tools
| Tool | Purpose | When to Use |
|------|---------|-------------|
| actions/checkout@v4 | Checkout repository | All workflows |
| codecov/codecov-action@v4 | Coverage reporting (optional) | If using Codecov for coverage tracking |
| chrnorm/deployment-action@v2 | Deployment status badges (optional) | For commit status decoration |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| appleboy/ssh-action | self-deployment script | More control, more maintenance, harder to debug |
| Xdebug | PCOV | PCOV faster but Xdebug more compatible, already in Docker image |
| git pull | rsync + git archive | Faster for large repos, but git pull simpler for small projects |
| GitHub Actions | GitLab CI, Jenkins | Already on GitHub, native Actions best for this project |

**Installation:**
```bash
# No installation needed - GitHub Actions runs in GitHub infrastructure
# Xdebug already available in Docker image (installed via pecl in Dockerfile)
# SSH key needs to be generated and added to GitHub Secrets
ssh-keygen -t ed25519 -C "github-actions-deploy" -f ~/.ssh/github_actions_deploy
```

## Architecture Patterns

### Recommended GitHub Actions Workflow Structure
```
.github/
└── workflows/
    ├── ci.yml              # Continuous Integration (test on push/PR)
    └── deploy-production.yml  # Deployment to production (main branch)
```

### Pattern 1: Laravel CI Workflow with MySQL Service
**What:** GitHub Actions workflow that runs PHPUnit tests with service containers
**When to use:** Every push and pull request to validate code quality
**Example:**
```yaml
# Source: GitHub Actions + Laravel standard patterns
name: CI

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  test:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: agency_sync_test
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, pdo, pdo_mysql, redis, bcmath, gd, zip
          coverage: xdebug

      - name: Copy .env.testing
        run: cp .env.example .env

      - name: Install Composer dependencies
        run: composer install --no-interaction --prefer-dist --optimize-autoloader

      - name: Generate application key
        run: php artisan key:generate

      - name: Clear config cache
        run: php artisan config:clear

      - name: Run tests with coverage
        run: vendor/bin/phpunit --coverage-clover=coverage.xml --coverage-text

      - name: Upload coverage to Codecov (optional)
        uses: codecov/codecov-action@v4
        with:
          files: ./coverage.xml
          fail_ci_if_error: false
```

### Pattern 2: SSH Deployment with Docker Compose
**What:** Deploy to self-hosted server via SSH, pull code, restart containers
**When to use:** On successful CI to main branch, deploy to production
**Example:**
```yaml
# Source: appleboy/ssh-action documentation + Docker Compose deployment patterns
name: Deploy to Production

on:
  push:
    branches: [ main ]
  workflow_dispatch:  # Allow manual trigger

jobs:
  deploy:
    runs-on: ubuntu-latest
    # Only run after CI passes
    if: github.ref == 'refs/heads/main'

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Deploy via SSH
        uses: appleboy/ssh-action@v1.0.0
        with:
          host: ${{ secrets.SERVER_HOST }}
          username: ${{ secrets.SERVER_USER }}
          key: ${{ secrets.SSH_PRIVATE_KEY }}
          port: ${{ secrets.SSH_PORT || 22 }}
          script_stop: true  # Stop on any error
          script: |
            cd /var/www/agency-sync

            # Pull latest code
            git pull origin main

            # Install/update dependencies
            composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

            # Clear Laravel caches
            php artisan config:clear
            php artisan route:clear
            php artisan view:clear
            php artisan cache:clear

            # Run database migrations
            php artisan migrate --force

            # Restart Docker containers (recreate if config changed)
            docker compose down
            docker compose up -d --build

            # Health check
            curl -f http://localhost/health || exit 1

            echo "Deployment successful!"
```

### Pattern 3: Deployment Script with Rollback
**What:** Server-side deployment script with error handling and rollback
**When to use:** For more complex deployments needing rollback capability
**Example:**
```bash
#!/bin/bash
# deploy.sh - Server-side deployment script

set -e  # Exit on error

APP_DIR="/var/www/agency-sync"
BACKUP_DIR="/var/backups/agency-sync"
LOG_FILE="/var/log/agency-sync/deploy.log"

# Log function
log() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Backup current version
backup() {
    log "Creating backup..."
    mkdir -p "$BACKUP_DIR"
    tar -czf "$BACKUP_DIR/backup-$(date +%Y%m%d-%H%M%S).tar.gz" -C "$APP_DIR" .
}

# Rollback on failure
rollback() {
    log "Deployment failed! Rolling back..."
    # Find latest backup and restore
    LATEST_BACKUP=$(ls -t "$BACKUP_DIR"/backup-*.tar.gz | head -1)
    tar -xzf "$LATEST_BACKUP" -C "$APP_DIR"
    docker compose up -d
    log "Rollback complete"
    exit 1
}

trap rollback ERR

# Main deployment
cd "$APP_DIR"
log "Pulling latest code..."
git pull origin main

log "Installing dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

log "Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

log "Running migrations..."
php artisan migrate --force

log "Restarting containers..."
docker compose down
docker compose up -d --build

log "Waiting for health check..."
sleep 10
curl -f http://localhost/health || { log "Health check failed"; exit 1; }

log "Deployment successful!"
```

### Pattern 4: PHPUnit Coverage Configuration
**What:** Configure PHPUnit to generate coverage reports with 70% threshold
**When to use:** In phpunit.xml for CI/CD enforcement
**Example:**
```xml
<!-- Source: PHPUnit 11.x documentation -->
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         cacheDirectory=".phpunit.cache"
         testdox="true"
         executionOrder="default"
         failOnRisky="true"
         failOnWarning="true">

    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
        <testsuite name="Browser">
            <directory>tests/Browser</directory>
        </testsuite>
    </testsuites>

    <source>
        <include>
            <directory suffix=".php">app</directory>
        </include>
        <exclude>
            <directory suffix=".php">app/Providers</directory>
            <file>app/Helpers/helpers.php</file>
        </exclude>
    </source>

    <!-- NEW: Coverage configuration -->
    <coverage>
        <report>
            <html outputDirectory="coverage/html"/>
            <text outputFile="php://stdout" showUncoveredFiles="false" showOnlySummary="true"/>
            <clover outputFile="coverage/clover.xml"/>
        </report>
        <!-- Enforce 70% coverage threshold -->
        <threshold>
            <global>
                <line value="70"/>
                <method value="70"/>
            </global>
        </threshold>
    </coverage>

    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="APP_MAINTENANCE_DRIVER" value="file"/>
        <env name="BCRYPT_ROUNDS" value="4"/>
        <env name="CACHE_STORE" value="array"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="PULSE_ENABLED" value="false"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="TELESCOPE_ENABLED" value="false"/>
        <env name="LOG_CHANNEL" value="null"/>
    </php>
</phpunit>
```

### Anti-Patterns to Avoid
- **Hardcoded credentials in workflow files:** Always use GitHub Secrets for sensitive data
- **Running tests without coverage:** defeats purpose of CI, need visibility into quality
- **Deploying on every branch:** Only deploy from main/master after tests pass
- **No health checks after deployment:** deploy doesn't mean app is working, verify with health endpoint
- **Using `composer install` without --no-dev in production:** installs test dependencies, bloated deployment
- **Restarting containers before migrations:** can cause downtime if migrations take time
- **Not clearing Laravel caches:** old cached routes/config can break app after deployment

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| PHP environment setup | Custom PHP installation scripts | `shivammathur/setup-php@v2` | Handles extensions, versions, coverage tools, caching |
| SSH connection handling | Custom SSH scripts with key management | `appleboy/ssh-action@v1.0.0` | Secure key handling, connection retries, output capturing |
| Code coverage calculation | Custom scripts parsing PHPUnit output | PHPUnit built-in coverage with Xdebug | Standard format, integrates with CI, HTML reports |
| Docker service containers | Custom Docker-in-Docker setup | GitHub Actions services container | Native support, better performance, no socket mounting |
| Test result formatting | Custom test reporters | PHPUnit with --testdox | Human-readable output, standard format, CI integration |
| Deployment orchestration | Custom bash scripts with error handling | GitHub Actions job dependencies | Visual workflow, parallel execution, artifact sharing |
| Health check endpoints | Custom curl scripts | `actions/upload-artifact` + `curl -f` | Built-in retry logic, better error messages, artifact storage |

**Key insight:** Building custom CI/CD tooling is a rabbit hole. Use battle-tested actions that handle edge cases, security, and integration. Focus on Laravel application logic, not infrastructure glue code.

## Common Pitfalls

### Pitfall 1: Coverage Report Missing from CI
**What goes wrong:** CI passes but no coverage metrics visible, can't track 70% goal
**Why it happens:** Xdebug not enabled in CI, or coverage not uploaded as artifact
**How to avoid:**
- Always use `coverage: xdebug` in setup-php action
- Upload coverage.xml as GitHub Actions artifact
- Use `--coverage-text` flag for console output
**Warning signs:** CI runs in 30 seconds (too fast for coverage), no coverage badge in README

### Pitfall 2: Deployment Fails Silently
**What goes wrong:** SSH command exits with 0 but app is broken, users see errors
**Why it happens:** Docker restart fails but script continues, no health check, or git pull conflicts
**How to avoid:**
- Use `set -e` or `script_stop: true` to exit on any error
- Implement health check endpoint (GET /health) that returns 200
- Use `curl -f` (fail on HTTP errors) for health checks
- Check git exit code after pull (conflicts return non-zero)
**Warning signs:** "Deployment successful" but app returns 500 errors

### Pitfall 3: Composer Dependencies Out of Sync
**What goes wrong:** Production deployment uses old composer.lock, tests pass locally but fail in prod
**Why it happens:** Forgetting to run `composer install` after git pull, or using --no-dev incorrectly
**How to avoid:**
- Always run `composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev` in production
- Commit composer.lock to git (Laravel default)
- Use `composer validate` in CI to check lock file consistency
**Warning signs:** "Class not found" errors after deployment

### Pitfall 4: Laravel Caches Not Cleared
**What goes wrong:** Deployment succeeds but old routes/config still active, features don't work
**Why it happens:** Laravel caches routes, config, views in bootstrap/cache and storage/framework
**How to avoid:**
- Always run `php artisan config:clear`, `route:clear`, `view:clear`, `cache:clear` after code pull
- Consider using `php artisan optimize:clear` to clear all caches at once
- Add cache clearing to deployment script as mandatory step
**Warning signs:** Changes not visible after deployment, 404 on new routes

### Pitfall 5: Migration Rollback Not Handled
**What goes wrong:** Migration fails, database partially migrated, app broken
**Why it happens:** Using `migrate --force` without testing migrations first, or migration has bugs
**How to avoid:**
- Test migrations in CI against copy of production database
- Use `migrate:fresh --seed` in testing, `migrate --force` in production
- Consider wrapping migrations in transaction (Laravel default)
- Have rollback plan: `php artisan migrate:rollback --step=1`
**Warning signs:** "SQLSTATE[42S02]: Base table or view not found" errors

### Pitfall 6: Docker Container Restart Race Conditions
**What goes wrong:** App container starts before MySQL ready, database connection errors
**Why it happens:** Docker Compose doesn't wait for service health by default
**How to avoid:**
- Use `depends_on: condition: service_healthy` in compose.yaml (already configured)
- Add health checks to all services (MySQL, Redis, Elasticsearch)
- Add sleep or retry logic in startup script if needed
- Use `docker compose up -d --no-deps` to restart specific services
**Warning signs:** "SQLSTATE[HY000] [2002] Connection refused" in logs

### Pitfall 7: SSH Key Permissions Issues
**What goes wrong:** GitHub Actions can't authenticate with server, deployment fails
**Why it happens:** SSH key not added to server's authorized_keys, or wrong key format
**How to avoid:**
- Generate ED25519 key pair: `ssh-keygen -t ed25519 -C "github-actions"`
- Add public key to server's `~/.ssh/authorized_keys`
- Add private key to GitHub Secrets as `SSH_PRIVATE_KEY` (including BEGIN/END markers)
- Test SSH connection manually first: `ssh -i private_key user@host`
**Warning signs:** "Permission denied (publickey)" in Actions logs

### Pitfall 8: Tests Pass in CI But Fail Locally (or Vice Versa)
**What goes wrong:** Different test results between local machine and CI
**Why it happens:** Different PHP versions, extensions, or environment configuration
**How to avoid:**
- Lock PHP version in composer.json: `"php": "^8.2"`
- Use same PHP version in CI as local: `php-version: '8.2'`
- Keep .env.testing updated with test database config
- Run tests locally with same command as CI: `vendor/bin/phpunit`
**Warning signs:** CI passes but `vendor/bin/phpunit` fails locally

## Code Examples

Verified patterns from official sources:

### GitHub Actions: Laravel CI with Services
```yaml
# Source: GitHub Actions documentation + Laravel best practices
name: Laravel CI

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  laravel-tests:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: agency_sync_test
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, pdo, pdo_mysql, redis, bcmath, gd, zip
          coverage: xdebug

      - name: Get Composer Cache Directory
        id: composer-cache
        run: echo "dir=$(composer config cache-files-dir)" >> $GITHUB_OUTPUT

      - name: Cache Composer dependencies
        uses: actions/cache@v4
        with:
          path: ${{ steps.composer-cache.outputs.dir }}
          key: ${{ runner.os }}-composer-${{ hashFiles('**/composer.lock') }}
          restore-keys: ${{ runner.os }}-composer-

      - name: Install Dependencies
        run: composer install --no-interaction --prefer-dist --optimize-autoloader

      - name: Copy .env
        run: cp .env.example .env

      - name: Generate Key
        run: php artisan key:generate

      - name: Run Tests
        run: vendor/bin/phpunit --coverage-clover=coverage.xml --coverage-text

      - name: Upload Coverage Reports
        uses: actions/upload-artifact@v4
        with:
          name: coverage-report
          path: coverage.xml
```

### GitHub Actions: SSH Deployment
```yaml
# Source: appleboy/ssh-action documentation
name: Deploy Production

on:
  push:
    branches: [ main ]
  workflow_dispatch:

jobs:
  deploy:
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Deploy via SSH
        uses: appleboy/ssh-action@v1.0.0
        with:
          host: ${{ secrets.SERVER_HOST }}
          username: ${{ secrets.SERVER_USER }}
          key: ${{ secrets.SSH_PRIVATE_KEY }}
          port: ${{ secrets.SSH_PORT || 22 }}
          script_stop: true
          script: |
            cd /var/www/agency-sync

            echo "Pulling latest code..."
            git pull origin main

            echo "Installing dependencies..."
            composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

            echo "Clearing caches..."
            php artisan config:clear
            php artisan route:clear
            php artisan view:clear
            php artisan cache:clear

            echo "Running migrations..."
            php artisan migrate --force

            echo "Restarting containers..."
            docker compose down
            docker compose up -d --build

            echo "Waiting for startup..."
            sleep 15

            echo "Health check..."
            curl -f http://localhost/health || exit 1

            echo "Deployment successful!"
```

### PHPUnit: Coverage Enforcement
```xml
<!-- Source: PHPUnit 11.x documentation -->
<coverage>
    <report>
        <html outputDirectory="coverage/html"/>
        <text outputFile="php://stdout" showUncoveredFiles="false" showOnlySummary="true"/>
        <clover outputFile="coverage/clover.xml"/>
    </report>
    <threshold>
        <global>
            <line value="70"/>
            <method value="70"/>
        </global>
    </threshold>
</coverage>
```

### Bash: Deployment Script with Error Handling
```bash
#!/bin/bash
# Source: Bash best practices + Laravel deployment patterns
set -e  # Exit on error
set -u  # Exit on undefined variable
set -o pipefail  # Exit on pipe failure

APP_DIR="/var/www/agency-sync"
LOG_FILE="/var/log/agency-sync/deploy.log"

log() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

cd "$APP_DIR" || exit 1

log "Starting deployment..."

# Pull code
log "Pulling latest code..."
git pull origin main

# Install dependencies
log "Installing dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# Clear caches
log "Clearing Laravel caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Run migrations
log "Running database migrations..."
php artisan migrate --force

# Restart containers
log "Restarting Docker containers..."
docker compose down
docker compose up -d --build

# Health check
log "Waiting for application to start..."
sleep 15

log "Running health check..."
if curl -f http://localhost/health; then
    log "Deployment successful!"
    exit 0
else
    log "Health check failed!"
    exit 1
fi
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Travis CI | GitHub Actions | 2019-2020 | Native GitHub integration, no external service needed |
| Codeship | GitHub Actions | 2020+ | Better free tier, integrated with PR checks |
| Deployer PHP | SSH scripts + Docker Compose | 2023+ | Docker-first deployment, simpler stack |
| PHPUnit 9.x | PHPUnit 11.x | 2024 | PHP 8.2+ support, improved coverage reporting |
| Composer v1 | Composer v2 | 2020 | Faster, better dependency resolution |

**Deprecated/outdated:**
- **Travis CI:** No longer free for open source, migrated to GitHub Actions
- **PHPUnit 9.x:** Doesn't support PHP 8.2+ features, use PHPUnit 11.x for Laravel 11
- **Composer v1:** EOL October 2022, use Composer v2.2+
- **PHP 7.x:** No security updates, Laravel 11 requires PHP 8.2+
- **Laravel 8.x, 9.x, 10.x:** EOL or security-only, use Laravel 11.x for active development

## Open Questions

1. **Should we implement zero-downtime deployment?**
   - What we know: Single-user agency tool, not consumer-facing, no 99.9% uptime requirement
   - What's unclear: User expectations for downtime during deployment
   - Recommendation: For v1, brief downtime (30-60 seconds) acceptable during deployment. Zero-downtime requires blue-green deployment or load balancer, adds complexity not justified for single-tenant use case.

2. **Should we use staging environment?**
   - What we know: No staging environment currently defined, only local + production
   - What's unclear: Whether user needs pre-production testing environment
   - Recommendation: Skip for v1. User can test locally, then deploy to production. Add staging in v2 if deploying to multiple client servers.

3. **Coverage enforcement: strict or threshold?**
   - What we know: Requirement is 70% minimum coverage, currently have good test coverage
   - What's unclear: Whether CI should fail if coverage drops below 70%, or just warn
   - Recommendation: Enforce 70% threshold in CI using PHPUnit `<threshold>` config. Failing build on low coverage ensures quality doesn't regress.

4. **Deployment notifications: Slack/email?**
   - What we know: No notification requirement specified, single-user tool
   - What's unclear: Whether user wants deployment success/failure notifications
   - Recommendation: Skip for v1. User can check GitHub Actions UI for deployment status. Add notifications in v2 if deploying to production servers regularly.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 11.0.1 |
| Config file | phpunit.xml (root directory) |
| Quick run command | `vendor/bin/phpunit --testsuite=Unit` |
| Full suite command | `vendor/bin/phpunit` |
| Coverage command | `XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html=coverage` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| CICD-01 | GitHub Actions runs tests on push to main | integration | N/A (workflow test) | ❌ Wave 0 |
| CICD-02 | GitHub Actions executes PHPUnit | integration | N/A (workflow test) | ❌ Wave 0 |
| CICD-03 | GitHub Actions deploys via SSH on success | integration | N/A (workflow test) | ❌ Wave 0 |
| CICD-04 | Deployment script runs git pull | unit/integration | `tests/Feature/Deployment/DeployScriptTest::test_git_pull` | ❌ Wave 0 |
| CICD-05 | Deployment script restarts Docker | unit/integration | `tests/Feature/Deployment/DeployScriptTest::test_docker_restart` | ❌ Wave 0 |
| CICD-06 | Deployment script clears Laravel cache | unit/integration | `tests/Feature/Deployment/DeployScriptTest::test_cache_clear` | ❌ Wave 0 |
| CICD-07 | Deployment script runs migrations | unit/integration | `tests/Feature/Deployment/DeployScriptTest::test_migrations` | ❌ Wave 0 |
| TEST-04 | Tests achieve 70% coverage | automated | `vendor/bin/phpunit --coverage-text` | ✅ Existing tests |
| TEST-05 | Tests run in CI/CD before deployment | automated | N/A (workflow runs tests) | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `vendor/bin/phpunit --testsuite=Unit` (Unit tests only, < 30s)
- **Per wave merge:** `vendor/bin/phpunit` (Full suite including Feature/Integration)
- **Phase gate:** `XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text` with 70% threshold enforced

### Wave 0 Gaps
- [ ] `.github/workflows/ci.yml` — GitHub Actions workflow for CI (CICD-01, CICD-02, TEST-05)
- [ ] `.github/workflows/deploy-production.yml` — Deployment workflow (CICD-03)
- [ ] `deploy.sh` — Server-side deployment script (CICD-04, CICD-05, CICD-06, CICD-07)
- [ ] `phpunit.xml` — Update with `<coverage>` section for 70% threshold (TEST-04)
- [ ] `tests/Feature/Deployment/DeployScriptTest.php` — Test deployment script logic (Wave 1+)
- [ ] `app/Http/Controllers/HealthController.php` — Health check endpoint for deployment verification

**Note:** CI/CD workflows themselves can't be easily tested automatically. Manual verification required after Wave 0 by:
1. Pushing to main branch and verifying workflow runs
2. Checking test execution and coverage reports
3. Testing SSH deployment to staging/production environment
4. Verifying health check endpoint works

## Sources

### Primary (HIGH confidence)
- **Laravel 11 Documentation** - Testing configuration, PHPUnit integration, artisan commands
- **PHPUnit 11.x Documentation** - Coverage configuration, thresholds, XML format
- **GitHub Actions Documentation** - Workflow syntax, service containers, secrets management
- **shivammathur/setup-php** - PHP setup action, extension configuration, Xdebug support
- **appleboy/ssh-action** - SSH deployment action, authentication, script execution

### Secondary (MEDIUM confidence)
- **Docker Compose v2 Documentation** - Container orchestration, health checks, restart policies
- **Xdebug 3.x Documentation** - Coverage mode configuration, performance considerations
- **Laravel Deployment Best Practices** - Cache clearing, optimization, zero-downtime strategies

### Tertiary (LOW confidence)
- **Community blog posts** - Specific Laravel CI/CD examples (verify with official docs)
- **GitHub Actions marketplace** - Community-created actions (check stars, last updated)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Official Laravel 11, PHPUnit 11, GitHub Actions patterns well-established
- Architecture: HIGH - Standard CI/CD patterns for PHP/Laravel projects, widely documented
- Pitfalls: HIGH - Common deployment issues well-documented in Laravel ecosystem

**Research date:** 2026-03-14
**Valid until:** 2026-04-14 (30 days - stable CI/CD patterns, but verify GitHub Actions syntax)

**Key assumptions:**
- Project is self-hosted (no Vercel/Heroku/etc.)
- Server has Docker Compose v2+ installed
- Server has SSH access configured
- Single production environment (no staging)
- 70% coverage threshold is minimum, not target (aim higher if possible)
