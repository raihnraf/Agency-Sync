# Requirements: AgencySync

**Defined:** 2026-03-13
**Core Value:** E-commerce agencies can reliably manage and synchronize product catalogs across multiple client stores with sub-second search performance and non-blocking background processing.

## v1 Requirements

Requirements for initial release. Each maps to roadmap phases.

### Authentication

- [x] **AUTH-01**: Agency admin can create account with email and password
- [x] **AUTH-02**: Agency admin can log in and session persists across requests
- [x] **AUTH-03**: Agency admin can log out from any page
- [x] **AUTH-04**: API endpoints are protected with authentication middleware

### Client/Tenant Management

- [x] **TENANT-01**: Agency admin can create new client store with name and platform type (Shopify/Shopware)
- [x] **TENANT-02**: Agency admin can view list of all client stores
- [x] **TENANT-03**: Agency admin can update client store details (name, status, platform URL)
- [x] **TENANT-04**: Agency admin can delete client store
- [x] **TENANT-05**: System stores API credentials encrypted in database (Shopify API key, Shopware credentials)
- [x] **TENANT-06**: Database uses tenant_id discriminator for multi-tenant data isolation
- [x] **TENANT-07**: Queries automatically scope to current tenant via global scopes

### Catalog Synchronization

- [ ] **SYNC-01**: Agency admin can trigger manual catalog sync for a specific client store
- [ ] **SYNC-02**: Sync operation runs asynchronously in background queue (non-blocking HTTP request)
- [ ] **SYNC-03**: System validates product data before storing (required fields, data types)
- [ ] **SYNC-04**: System implements retry logic with exponential backoff for failed API calls
- [ ] **SYNC-05**: System logs all sync operations (start time, end time, status, error messages)
- [x] **SYNC-06**: Agency admin can view sync status for each client store (pending, running, completed, failed)
- [ ] **SYNC-07**: System fetches product data from Shopify API (products, variants, inventory)
- [ ] **SYNC-08**: System fetches product data from Shopware API (products, variants, inventory)
- [x] **SYNC-09**: System stores product data in MySQL with tenant_id association

### Product Search

- [ ] **SEARCH-01**: Agency admin can search products within a single client's catalog
- [ ] **SEARCH-02**: Search returns results in sub-second time (< 500ms for typical queries)
- [ ] **SEARCH-03**: Search supports fuzzy matching (tolerates typos, partial matches)
- [ ] **SEARCH-04**: Search results are paginated (20 products per page)
- [ ] **SEARCH-05**: System indexes product data in Elasticsearch for fast search
- [ ] **SEARCH-06**: Elasticsearch index is scoped per tenant (tenant_id filter)
- [ ] **SEARCH-07**: Search results only include products from selected client store (tenant isolation)

### Background Jobs

- [ ] **QUEUE-01**: System uses Redis for queue storage
- [ ] **QUEUE-02**: Supervisor monitors and restarts queue workers
- [ ] **QUEUE-03**: Queue jobs include tenant_id in payload for tenant context
- [ ] **QUEUE-04**: System tracks job status (pending, running, completed, failed)
- [ ] **QUEUE-05**: Failed jobs automatically retry with exponential backoff (3 attempts max)
- [ ] **QUEUE-06**: System logs all job failures with error details
- [ ] **QUEUE-07**: Agency admin can view queue job status in admin dashboard

### Admin Dashboard

- [ ] **UI-01**: Agency admin can view client store list with status indicators
- [ ] **UI-02**: Agency admin can create new client store via form (name, platform, API credentials)
- [x] **UI-03**: Agency admin can edit client store details
- [x] **UI-04**: Agency admin can delete client store with confirmation
- [ ] **UI-05**: Agency admin can trigger sync operation for each client store
- [ ] **UI-06**: Agency admin can view last sync status for each client store (time, status, product count)
- [ ] **UI-07**: Agency admin can search products within a client's catalog
- [ ] **UI-08**: Agency admin can view error log with filtering by client store and date
- [ ] **UI-09**: Dashboard uses Blade templates with Alpine.js for interactivity
- [ ] **UI-10**: Dashboard uses TailwindCSS for styling
- [ ] **UI-11**: Dashboard is responsive for mobile and tablet viewing

### Infrastructure

- [x] **INFRA-01**: System uses Docker Compose to run all services
- [x] **INFRA-02**: MySQL 8.0 container for relational database
- [x] **INFRA-03**: Elasticsearch container for product search indexing
- [x] **INFRA-04**: Redis container for queue storage
- [x] **INFRA-05**: Nginx container as reverse proxy to PHP-FPM
- [x] **INFRA-06**: Laravel Sail extended with custom services (Elasticsearch, Redis)
- [x] **INFRA-07**: Environment configuration via .env files for all containers
- [x] **INFRA-08**: System can start with single command (docker-compose up)

### API Design

- [ ] **API-01**: API uses RESTful design principles
- [x] **API-02**: API endpoints are versioned (/api/v1/)
- [ ] **API-03**: API returns JSON responses with consistent structure
- [x] **API-04**: API uses appropriate HTTP status codes (200, 201, 400, 401, 404, 500)
- [x] **API-05**: API implements rate limiting per authenticated user
- [x] **API-06**: API validates request data before processing
- [x] **API-07**: API returns error messages with actionable details

### Data Flows

- [x] **DATAFLOW-01**: Agency admin can export sync logs to CSV file
- [x] **DATAFLOW-02**: Agency admin can export product catalog to CSV/Excel file
- [x] **DATAFLOW-03**: Export includes tenant information, timestamps, and status

### Web Caching

- [x] **CACHE-01**: Dashboard metrics are cached for 5 minutes using Redis
- [x] **CACHE-02**: Tenant list is cached using Cache::remember()
- [x] **CACHE-03**: Cache invalidates on data updates

### Operations & Logging

- [x] **OPS-01**: Server logging documentation covers Nginx access/error logs
- [x] **OPS-02**: Server logging documentation covers Laravel logs
- [x] **OPS-03**: Server logging documentation covers Supervisor worker logs

### CI/CD & Deployment

- [x] **CICD-01**: GitHub Actions workflow runs automated tests on push to main branch
- [x] **CICD-02**: GitHub Actions workflow executes PHPUnit tests
- [x] **CICD-03**: GitHub Actions workflow deploys to server via SSH on successful tests
- [x] **CICD-04**: Deployment script runs git pull on remote server
- [x] **CICD-05**: Deployment script restarts Docker containers after code update
- [x] **CICD-06**: Deployment script clears Laravel cache (config, routes, views)
- [x] **CICD-07**: Deployment script runs database migrations

### Testing

- [x] **TEST-01**: System has unit tests for core business logic (tenant scoping, validation)
- [x] **TEST-02**: System has feature tests for API endpoints
- [ ] **TEST-03**: System has integration tests for queue jobs
- [x] **TEST-04**: Tests achieve minimum 70% code coverage
- [x] **TEST-05**: Tests run in CI/CD pipeline before deployment

## v2 Requirements

Deferred to future release. Tracked but not in current roadmap.

### Cross-Client Search

- **SEARCH-50**: Agency admin can search products across all client catalogs
- **SEARCH-51**: Unified Elasticsearch index for cross-client search
- **SEARCH-52**: Search results show which client store each product belongs to

### Automation

- **SYNC-50**: Agency admin can schedule automated sync (hourly, daily, weekly)
- **SYNC-51**: System processes webhooks from Shopify/Shopware for real-time sync
- **SYNC-52**: System implements delta sync (only changed products)

### Advanced Features

- **TENANT-50**: Bulk client operations (import, status updates)
- **SEARCH-50**: Advanced search filters (price range, stock status, category)
- **UI-50**: Performance metrics dashboard (sync speed, API usage)

### API Documentation

- **APIDOCS-01**: System generates interactive API documentation from Laravel docblocks and route definitions
- **APIDOCS-02**: API documentation accessible at `/docs` endpoint with clean, modern UI
- **APIDOCS-03**: All API endpoints documented with request/response examples and authentication methods
- **APIDOCS-04**: Documentation includes curl command examples for each endpoint
- **APIDOCS-05**: Response schemas and validation rules clearly documented for each endpoint

### Audit Logs & Debugging

- [x] **AUDIT-01**: Sync Logs table includes "View Details" button for each log entry
- [x] **AUDIT-02**: Failed syncs display raw JSON error payloads from external APIs (Shopify, Shopware)
- [x] **AUDIT-03**: System captures and displays Laravel stack traces for internal errors
- [x] **AUDIT-04**: Error details include timestamps, error codes, and full context in formatted JSON
- [x] **AUDIT-05**: Rate limiting errors and API failures clearly shown with actionable error messages

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Real-time sync via WebSocket | Adds massive complexity, polling/periodic sync sufficient for v1 |
| Client-facing UI | Dilutes agency focus, clients use native platform UI |
| Full order management | Bloated scope, orders stay in native e-commerce platforms |
| Multi-user roles | Single admin user sufficient for v1 |
| OAuth login | Email/password authentication sufficient for v1 |
| Multi-currency support | Not relevant for agency backend tool |
| Predictive inventory | Requires historical data, ML infrastructure |
| Payment processing | PCI compliance nightmare, payments in native platforms |
| Marketing automation | Entirely different domain, integrate via webhooks |
| Mobile applications | Web-first, mobile-responsive dashboard sufficient |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| AUTH-01 | Phase 2 | Complete |
| AUTH-02 | Phase 2 | Complete |
| AUTH-03 | Phase 2 | Complete |
| AUTH-04 | Phase 2 | Complete |
| TENANT-01 | Phase 3 | Complete |
| TENANT-02 | Phase 3 | Complete |
| TENANT-03 | Phase 3 | Complete |
| TENANT-04 | Phase 3 | Complete |
| TENANT-05 | Phase 3 | Complete |
| TENANT-06 | Phase 3 | Complete |
| TENANT-07 | Phase 3 | Complete |
| SYNC-01 | Phase 6 | Complete |
| SYNC-02 | Phase 4 | Complete |
| SYNC-03 | Phase 6 | Complete |
| SYNC-04 | Phase 4 | Complete |
| SYNC-05 | Phase 6 | Complete |
| SYNC-06 | Phase 6 | Complete |
| SYNC-07 | Phase 6 | Complete |
| SYNC-08 | Phase 6 | Complete |
| SYNC-09 | Phase 6 | Complete |
| SEARCH-01 | Phase 5 | Complete |
| SEARCH-02 | Phase 5 | Complete |
| SEARCH-03 | Phase 5 | Complete |
| SEARCH-04 | Phase 5 | Complete |
| SEARCH-05 | Phase 5 | Complete |
| SEARCH-06 | Phase 5 | Complete |
| SEARCH-07 | Phase 5 | Complete |
| QUEUE-01 | Phase 4 | Complete |
| QUEUE-02 | Phase 4 | Complete |
| QUEUE-03 | Phase 4 | Complete |
| QUEUE-04 | Phase 4 | Complete |
| QUEUE-05 | Phase 4 | Complete |
| QUEUE-06 | Phase 4 | Complete |
| QUEUE-07 | Phase 7 | Complete |
| UI-01 | Phase 7 | Complete |
| UI-02 | Phase 7 | Complete |
| UI-03 | Phase 7 | Complete |
| UI-04 | Phase 7 | Complete |
| UI-05 | Phase 7 | Complete |
| UI-06 | Phase 7 | Complete |
| UI-07 | Phase 7 | Complete |
| UI-08 | Phase 7 | Complete |
| UI-09 | Phase 7 | Complete |
| UI-10 | Phase 7 | Complete |
| UI-11 | Phase 7 | Complete |
| INFRA-01 | Phase 1 | Complete |
| INFRA-02 | Phase 1 | Complete |
| INFRA-03 | Phase 1 | Complete |
| INFRA-04 | Phase 1 | Complete |
| INFRA-05 | Phase 1 | Complete |
| INFRA-06 | Phase 1 | Complete |
| INFRA-07 | Phase 1 | Complete |
| INFRA-08 | Phase 1 | Complete |
| API-01 | Phase 2 | Complete |
| API-02 | Phase 2 | Complete |
| API-03 | Phase 2 | Complete |
| API-04 | Phase 2 | Complete |
| API-05 | Phase 2 | Complete |
| API-06 | Phase 2 | Complete |
| API-07 | Phase 2 | Complete |
| CICD-01 | Phase 8 | Complete |
| CICD-02 | Phase 8 | Complete |
| CICD-03 | Phase 8 | Complete |
| CICD-04 | Phase 8 | Complete |
| CICD-05 | Phase 8 | Complete |
| CICD-06 | Phase 8 | Complete |
| CICD-07 | Phase 8 | Complete |
| TEST-01 | Phase 3 | Complete |
| TEST-02 | Phase 3 | Complete |
| TEST-03 | Phase 4 | Complete |
| TEST-04 | Phase 10 | Complete |
| TEST-05 | Phase 10 | Complete |
| APIDOCS-01 | Phase 11 | Complete |
| APIDOCS-02 | Phase 11 | Complete |
| APIDOCS-03 | Phase 11 | Complete |
| APIDOCS-04 | Phase 11 | Complete |
| APIDOCS-05 | Phase 11 | Complete |
| AUDIT-01 | Phase 12 | Complete |
| AUDIT-02 | Phase 12 | Complete |
| AUDIT-03 | Phase 12 | Complete |
| AUDIT-04 | Phase 12 | Complete |
| AUDIT-05 | Phase 12 | Complete |

**Coverage:**
- v1 requirements: 70 total
- Mapped to phases: 70
- Unmapped: 0 ✓

---
*Requirements defined: 2026-03-13*
*Last updated: 2026-03-15 - Added Phase 11 (API Documentation) and Phase 12 (Audit Logs) for DOITSUYA portfolio enhancement*
