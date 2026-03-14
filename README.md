# AgencySync

[![PHP Version](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11.x-red.svg)](https://laravel.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![CI/CD](https://img.shields.io/badge/CI%2FCD-GHub%20Actions-success.svg)](.github/workflows/ci.yml)

A sophisticated multi-tenant SaaS platform built for digital agencies to manage multiple clients through a unified dashboard. Features tenant isolation, product catalog synchronization, automated data flows, and comprehensive testing infrastructure.

## 🚀 Tech Stack

### Core Technologies
- **PHP 8.2+** - Modern PHP features, JIT compiler, and improved performance
- **Laravel 11** - Latest Laravel framework with elegant syntax and robust features
- **MySQL 8.0** - Advanced database with JSON support, CTEs, and window functions
- **Redis 7** - High-performance caching and queue management
- **Elasticsearch 8.13** - Full-text search and analytics engine

### Infrastructure & DevOps
- **Docker & Docker Compose v2** - Container orchestration with health checks
- **Nginx** - High-performance reverse proxy
- **GitHub Actions CI/CD** - Automated testing with 70% coverage enforcement
- **PHPUnit** - Comprehensive testing suite with 90+ test files
- **Xdebug** - Code coverage analysis and debugging

### Key Libraries & Tools
- **Laravel Sanctum** - API authentication and token management
- **Laravel Queues** - Background job processing with Redis
- **Maatwebsite Excel** - Import/export functionality (CSV, Excel)
- **Eloquent ORM** - Advanced database relationships and querying

## ✨ Features

### Multi-Tenant Architecture
- **Tenant Isolation** - Complete data separation between agencies
- **Tenant-Specific Dashboards** - Custom views per organization
- **Dynamic Tenant Registration** - Self-service agency onboarding
- **Tenant Metrics Caching** - Performance optimization with Redis

### Product Catalog Management
- **Bulk Product Import** - CSV/Excel import with validation
- **Product Synchronization** - Automated updates from external sources
- **Catalog Export** - Generate product feeds in multiple formats
- **Real-time Inventory Tracking** - Stock management across tenants

### API & Integrations
- **RESTful API** - Well-documented API endpoints
- **API Token Authentication** - Secure token-based access via Laravel Sanctum
- **Export Functionality** - Background jobs for large data exports
- **Health Check Endpoints** - System monitoring and deployment verification

### Data Processing
- **CSV/XML/JSON Processing** - Handle multiple data formats
- **Background Job Processing** - Queue-based async operations
- **Automated Workflows** - Scheduled tasks and data flows
- **Export Scheduling** - Automated report generation

### Operations & Monitoring
- **Comprehensive Logging** - Detailed application logs
- **Cache Management** - Multi-layer caching strategy (Redis, application)
- **Deployment Automation** - SSH-based deployment with zero-downtime
- **Database Migrations** - Version-controlled schema changes

## 🏗️ Architecture Highlights

### System Design
```
┌─────────────────────────────────────────────────────────────┐
│                        Nginx Reverse Proxy                  │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│                    Laravel 11 Application                   │
│  ┌──────────────────────────────────────────────────────┐  │
│  │              Multi-Tenant Middleware                  │  │
│  │  ┌────────────┐  ┌────────────┐  ┌────────────┐    │  │
│  │  │  Tenant A  │  │  Tenant B  │  │  Tenant C  │    │  │
│  │  └────────────┘  └────────────┘  └────────────┘    │  │
│  └──────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────┐  │
│  │              Service Layer                            │  │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐           │  │
│  │  │ Products │ │  Sync    │ │  Export  │           │  │
│  │  └──────────┘ └──────────┘ └──────────┘           │  │
│  └──────────────────────────────────────────────────────┘  │
└────┬────────────┬────────────┬────────────┬─────────────────┘
     │            │            │            │
┌────▼────┐  ┌───▼──────┐  ┌──▼─────┐  ┌───▼──────────┐
│ MySQL   │  │ Redis   │  │ Queue  │  │ Elasticsearch│
│ 8.0     │  │  7      │  │ Worker │  │   8.13       │
└─────────┘  └─────────┘  └────────┘  └──────────────┘
```

### Database Schema
- **Tenants** - Agency/organization management
- **Products** - Multi-tenant product catalog
- **Users** - User authentication with tenant association
- **API Tokens** - Secure API access management
- **Jobs** - Background job tracking

## 🧪 Testing

### Test Coverage
- **90+ Test Files** - Comprehensive test suite
- **70%+ Code Coverage** - Enforced via CI/CD
- **Unit Tests** - Isolated component testing
- **Feature Tests** - End-to-end API testing
- **Integration Tests** - Database and service integration

### Test Automation
```bash
# Run entire test suite
./vendor/bin/phpunit

# Run with coverage
./vendor/bin/phpunit --coverage-html=coverage/html

# Run specific test suite
./vendor/bin/phpunit --testsuite=Unit
./vendor/bin/phpunit --testsuite=Feature
```

## 🚀 Deployment

### CI/CD Pipeline
- **Automated Testing** - Tests run on every push to main
- **Code Quality Checks** - Coverage thresholds enforced
- **Automated Deployment** - Zero-downtime deployments via SSH
- **Health Checks** - Post-deployment verification

### Manual Deployment
```bash
# Deploy to production
./deploy.sh

# The deployment script:
# 1. Pulls latest code from git
# 2. Installs composer dependencies (production)
# 3. Runs database migrations
# 4. Clears Laravel caches (config, routes, views)
# 5. Restarts Docker containers
# 6. Verifies application health
```

## 📦 Installation

### Prerequisites
- Docker & Docker Compose v2
- PHP 8.2+ (for local development)
- Composer
- MySQL 8.0+ (if not using Docker)

### Quick Start with Docker
```bash
# Clone the repository
git clone https://github.com/raihnraf/Agency-Sync.git
cd Agency-Sync

# Copy environment configuration
cp .env.example .env

# Start all services
docker compose up -d

# Install dependencies
docker compose exec app composer install

# Generate application key
docker compose exec app php artisan key:generate

# Run migrations
docker compose exec app php artisan migrate

# Create a tenant
docker compose exec app php artisan tenant:create

# Run tests
docker compose exec app ./vendor/bin/phpunit
```

### Local Development
```bash
# Install dependencies
composer install

# Environment setup
cp .env.example .env
php artisan key:generate

# Database setup
php artisan migrate

# Run development server
php artisan serve

# Run tests
./vendor/bin/phpunit
```

## 📊 Project Statistics

- **Total Commits:** 100+
- **Lines of Code:** ~15,000+
- **Test Files:** 90+
- **Test Coverage:** 70%+
- **Active Development:** Started 2026
- **Documentation:** Comprehensive API docs and architecture guides

## 🔧 Configuration

### Environment Variables
```env
APP_NAME=AgencySync
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=agencysync
DB_USERNAME=agencysync
DB_PASSWORD=secret_password

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
```

## 📈 Performance Optimizations

- **Redis Caching** - Multi-layer caching strategy
- **Database Query Optimization** - Eager loading, indexing, query caching
- **Queue Management** - Background job processing for heavy operations
- **Asset Optimization** - Minification and bundling
- **HTTP Caching** - ETags and cache headers

## 🔒 Security Features

- **API Token Authentication** - Laravel Sanctum for secure API access
- **Tenant Data Isolation** - Complete separation of tenant data
- **CSRF Protection** - Cross-site request forgery prevention
- **SQL Injection Prevention** - Parameterized queries via Eloquent ORM
- **XSS Protection** - Input sanitization and output escaping
- **Password Hashing** - Bcrypt hashing with automatic rehashing

## 📚 Documentation

- **API Documentation** - Complete API reference with examples
- **Architecture Docs** - System design and data flow diagrams
- **Deployment Guides** - Production deployment procedures
- **Testing Guides** - Test writing and debugging resources

## 🤝 Contributing

This is a personal portfolio project showcasing advanced PHP/Laravel development skills. Feel free to explore the codebase and reach out for collaboration opportunities.

## 👨‍💻 Developer

**Raihan Rafi** - Full Stack Developer

- **GitHub:** [@raihnraf](https://github.com/raihnraf)
- **Location:** Tokyo, Japan (Remote available)
- **Email:** raihan@example.com

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🎯 Why This Project Matters

AgencySync demonstrates production-ready PHP development with:

✅ **Modern PHP 8.2+** - Leveraging latest PHP features and best practices
✅ **Enterprise Architecture** - Multi-tenant SaaS design patterns
✅ **DevOps Excellence** - Docker, CI/CD, automated deployments
✅ **Testing Culture** - 70%+ coverage with comprehensive test suite
✅ **Performance Focus** - Caching, queues, query optimization
✅ **Security First** - Authentication, authorization, data isolation
✅ **Production Experience** - Real-world deployment and monitoring

Built with passion for clean code, scalable architecture, and continuous learning.

---

**Note:** This project is continuously evolving with new features and improvements. Check the commit history for recent updates and enhancements.
