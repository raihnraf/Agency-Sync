# AgencySync Roadmap

**Project:** AgencySync - Multi-tenant E-commerce Agency Management System
**Created:** 2026-03-13
**Granularity:** Fine
**Phases:** 8

## Overview

AgencySync is a multi-tenant API-first backend system for e-commerce agencies to manage multiple client stores efficiently. This roadmap delivers a complete system with tenant isolation, catalog synchronization from Shopify/Shopware, Elasticsearch-powered search, and background job processing.

**Core Value:** E-commerce agencies can reliably manage and synchronize product catalogs across multiple client stores with sub-second search performance and non-blocking background processing.

## Phases

- [x] **Phase 1: Foundation & Infrastructure** - Docker containerization and Laravel 11 base setup (completed 2026-03-12)
- [ ] **Phase 2: Authentication & API Foundation** - Agency admin authentication and RESTful API structure
- [ ] **Phase 3: Tenant Management System** - Multi-tenant architecture with client store management
- [ ] **Phase 4: Background Processing Infrastructure** - Redis queues with Supervisor for async operations
- [ ] **Phase 5: Elasticsearch Integration** - Sub-second product search with fuzzy matching
- [ ] **Phase 6: Catalog Synchronization** - Shopify/Shopware integration with async sync workflows
- [ ] **Phase 7: Admin Dashboard** - Complete admin UI with Blade + Alpine.js
- [ ] **Phase 8: CI/CD & Testing** - Deployment pipeline and automated test coverage

## Phase Details

### Phase 1: Foundation & Infrastructure

**Goal:** Development environment is containerized and ready for team collaboration

**Depends on:** Nothing (first phase)

**Requirements:** INFRA-01, INFRA-02, INFRA-03, INFRA-04, INFRA-05, INFRA-06, INFRA-07, INFRA-08

**Success Criteria** (what must be TRUE):
1. Developer can start entire application stack with single command (`make up`)
2. All services (MySQL, Elasticsearch, Redis, Nginx, PHP-FPM) run in isolated containers
3. Environment configuration works via .env files for all containers
4. Laravel application serves requests through Nginx reverse proxy
5. Elasticsearch cluster is accessible for indexing operations

**Plans:** 3/3 plans complete
- [ ] 01-01-PLAN.md — Docker Compose v2 setup with all services (MySQL, Elasticsearch, Redis, Nginx, PHP-FPM), Makefile interface, and environment configuration

---

### Phase 2: Authentication & API Foundation

**Goal:** Agency admin can securely access the system via authenticated API requests

**Depends on:** Phase 1 (Foundation & Infrastructure)

**Requirements:** AUTH-01, AUTH-02, AUTH-03, AUTH-04, API-01, API-02, API-03, API-04, API-05, API-06, API-07

**Success Criteria** (what must be TRUE):
1. Agency admin can create account with email/password via API endpoint
2. Agency admin can log in and receive authentication token
3. Agency admin can access protected API endpoints using authentication token
4. Agency admin can log out and invalidate session
5. API returns consistent JSON structure with appropriate HTTP status codes
6. API validates request data and returns actionable error messages
7. API endpoints are versioned (/api/v1/) and use RESTful design principles
8. API implements rate limiting per authenticated user

**Plans:** 4 plans

- [ ] 02-01-PLAN.md — Laravel Sanctum authentication with registration, login, logout, protected endpoints, and test coverage
- [x] 02-02-PLAN.md — Consistent JSON response structure and validation error formatting with API Resources and Form Requests (completed 2026-03-13)
- [ ] 02-03-PLAN.md — API versioning with /api/v1/ prefix and proper HTTP status codes following RESTful principles
- [ ] 02-04-PLAN.md — Rate limiting per authenticated user and 4-hour token inactivity expiration

---

### Phase 3: Tenant Management System

**Goal:** Agency admin can manage multiple client stores with complete data isolation

**Depends on:** Phase 2 (Authentication & API Foundation)

**Requirements:** TENANT-01, TENANT-02, TENANT-03, TENANT-04, TENANT-05, TENANT-06, TENANT-07, TEST-01, TEST-02

**Success Criteria** (what must be TRUE):
1. Agency admin can create new client store with name, platform type, and API credentials
2. Agency admin can view list of all client stores
3. Agency admin can update client store details (name, status, platform URL)
4. Agency admin can delete client store
5. API credentials are stored encrypted in database
6. All database queries automatically scope to current tenant via global scopes
7. Database uses tenant_id discriminator for multi-tenant data isolation
8. Unit tests verify tenant scoping logic prevents cross-tenant data access
9. Feature tests verify API endpoints for tenant management

**Plans:** TBD

---

### Phase 4: Background Processing Infrastructure

**Goal:** System can process long-running tasks asynchronously without blocking HTTP requests

**Depends on:** Phase 3 (Tenant Management System)

**Requirements:** QUEUE-01, QUEUE-02, QUEUE-03, QUEUE-04, QUEUE-05, QUEUE-06, TEST-03, SYNC-02, SYNC-04

**Success Criteria** (what must be TRUE):
1. System uses Redis for queue storage
2. Supervisor monitors and restarts queue workers automatically
3. Queue jobs include tenant_id in payload for proper context
4. System tracks job status (pending, running, completed, failed)
5. Failed jobs automatically retry with exponential backoff (3 attempts max)
6. System logs all job failures with error details
7. Sync operations run asynchronously in background queue (non-blocking HTTP)
8. System implements retry logic with exponential backoff for failed API calls
9. Integration tests verify queue job processing with tenant context

**Plans:** TBD

---

### Phase 5: Elasticsearch Integration

**Goal:** System delivers sub-second search performance across product catalogs

**Depends on:** Phase 4 (Background Processing Infrastructure)

**Requirements:** SEARCH-01, SEARCH-02, SEARCH-03, SEARCH-04, SEARCH-05, SEARCH-06, SEARCH-07, QUEUE-07

**Success Criteria** (what must be TRUE):
1. Agency admin can search products within a single client's catalog
2. Search returns results in sub-second time (< 500ms for typical queries)
3. Search supports fuzzy matching (tolerates typos, partial matches)
4. Search results are paginated (20 products per page)
5. System indexes product data in Elasticsearch for fast search
6. Elasticsearch index is scoped per tenant (tenant_id filter)
7. Search results only include products from selected client store (tenant isolation)
8. Agency admin can view queue job status in admin dashboard

**Plans:** TBD

---

### Phase 6: Catalog Synchronization

**Goal:** Agency admin can synchronize product catalogs from Shopify/Shopware platforms

**Depends on:** Phase 5 (Elasticsearch Integration)

**Requirements:** SYNC-01, SYNC-03, SYNC-05, SYNC-06, SYNC-07, SYNC-08, SYNC-09

**Success Criteria** (what must be TRUE):
1. Agency admin can trigger manual catalog sync for a specific client store
2. System validates product data before storing (required fields, data types)
3. System logs all sync operations (start time, end time, status, error messages)
4. Agency admin can view sync status for each client store (pending, running, completed, failed)
5. System fetches product data from Shopify API (products, variants, inventory)
6. System fetches product data from Shopware API (products, variants, inventory)
7. System stores product data in MySQL with tenant_id association

**Plans:** TBD

---

### Phase 7: Admin Dashboard

**Goal:** Agency admin can manage entire system through responsive web interface

**Depends on:** Phase 5 (Elasticsearch Integration), Phase 6 (Catalog Synchronization)

**Requirements:** UI-01, UI-02, UI-03, UI-04, UI-05, UI-06, UI-07, UI-08, UI-09, UI-10, UI-11

**Success Criteria** (what must be TRUE):
1. Agency admin can view client store list with status indicators
2. Agency admin can create new client store via form (name, platform, API credentials)
3. Agency admin can edit client store details
4. Agency admin can delete client store with confirmation
5. Agency admin can trigger sync operation for each client store
6. Agency admin can view last sync status for each client store (time, status, product count)
7. Agency admin can search products within a client's catalog
8. Agency admin can view error log with filtering by client store and date
9. Dashboard uses Blade templates with Alpine.js for interactivity
10. Dashboard uses TailwindCSS for styling
11. Dashboard is responsive for mobile and tablet viewing

**Plans:** TBD

---

### Phase 8: CI/CD & Testing

**Goal:** System has automated deployment pipeline with comprehensive test coverage

**Depends on:** Phase 7 (Admin Dashboard)

**Requirements:** CICD-01, CICD-02, CICD-03, CICD-04, CICD-05, CICD-06, CICD-07, TEST-04, TEST-05

**Success Criteria** (what must be TRUE):
1. GitHub Actions workflow runs automated tests on push to main branch
2. GitHub Actions workflow executes PHPUnit tests
3. GitHub Actions workflow deploys to server via SSH on successful tests
4. Deployment script runs git pull on remote server
5. Deployment script restarts Docker containers after code update
6. Deployment script clears Laravel cache (config, routes, views)
7. Deployment script runs database migrations
8. Tests achieve minimum 70% code coverage
9. Tests run in CI/CD pipeline before deployment

**Plans:** TBD

---

## Progress Tracking

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Foundation & Infrastructure | 3/3 | Complete   | 2026-03-12 |
| 2. Authentication & API Foundation | 1/4 | In progress | - |
| 3. Tenant Management System | 0/0 | Not started | - |
| 4. Background Processing Infrastructure | 0/0 | Not started | - |
| 5. Elasticsearch Integration | 0/0 | Not started | - |
| 6. Catalog Synchronization | 0/0 | Not started | - |
| 7. Admin Dashboard | 0/0 | Not started | - |
| 8. CI/CD & Testing | 0/0 | Not started | - |

**Overall Progress:** 1/8 phases complete (12.5%)

## Dependencies

```
Phase 1 (Foundation)
    ↓
Phase 2 (Auth & API)
    ↓
Phase 3 (Tenant Management)
    ↓
Phase 4 (Background Processing)
    ↓
Phase 5 (Elasticsearch)
    ↓
Phase 6 (Catalog Sync)
    ↓
Phase 7 (Admin Dashboard) ← Depends on Phase 5 also
    ↓
Phase 8 (CI/CD & Testing)
```

## Requirements Coverage

**Total v1 Requirements:** 60
**Covered:** 60/60 (100%)

### Coverage Map

**Phase 1 (Foundation):** 8 requirements
- INFRA-01 through INFRA-08

**Phase 2 (Auth & API):** 11 requirements
- AUTH-01 through AUTH-04
- API-01 through API-07

**Phase 3 (Tenant Management):** 11 requirements
- TENANT-01 through TENANT-07
- TEST-01, TEST-02

**Phase 4 (Background Processing):** 9 requirements
- QUEUE-01 through QUEUE-06
- TEST-03
- SYNC-02, SYNC-04

**Phase 5 (Elasticsearch):** 8 requirements
- SEARCH-01 through SEARCH-07
- QUEUE-07

**Phase 6 (Catalog Sync):** 7 requirements
- SYNC-01, SYNC-03, SYNC-05 through SYNC-09

**Phase 7 (Admin Dashboard):** 11 requirements
- UI-01 through UI-11

**Phase 8 (CI/CD & Testing):** 9 requirements
- CICD-01 through CICD-07
- TEST-04, TEST-05

---

*Roadmap created: 2026-03-13*
*Last updated: 2026-03-13*
