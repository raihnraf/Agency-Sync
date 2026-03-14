### Phase 8: Hybrid Authentication

**Goal:** Agency users can access admin dashboard through web interface with session-based authentication while maintaining API-first architecture

**Depends on:** Phase 7 (Admin Dashboard)

**Requirements:** AUTH-WEB-01, AUTH-WEB-02, AUTH-WEB-03, AUTH-WEB-04, AUTH-WEB-05

**Success Criteria** (what must be TRUE):
1. Laravel Breeze (Blade edition) installed and configured
2. Web routes (`routes/web.php`) use session-based authentication
3. API routes (`routes/api.php`) continue using Sanctum token authentication
4. Login page accessible at `/login` with email/password form
5. Dashboard routes protected by `auth` middleware redirect unauthenticated users to login
6. Session management works correctly (login, logout, remember me)
7. Blade views render correctly with Alpine.js interactivity
8. No conflicts between API and web authentication systems
9. Portfolio-ready: working login → dashboard flow for employer demos

**Plans:** 7/7 plans complete

- [ ] 08-00-PLAN.md — Wave 0: Create test stubs for hybrid authentication (AUTH-WEB-01, AUTH-WEB-02, AUTH-WEB-03, AUTH-WEB-04, AUTH-WEB-05)
- [ ] 08-01-PLAN.md — Install Laravel Breeze (Blade edition) for session-based authentication scaffolding (AUTH-WEB-01)
- [ ] 08-02-PLAN.md — Configure web routes for session auth and API routes for Sanctum token auth (AUTH-WEB-02, AUTH-WEB-03)
- [ ] 08-03-PLAN.md — Remove registration routes to prevent self-registration (AUTH-WEB-04)
- [ ] 08-04-PLAN.md — Configure logout redirect to home page (/) instead of /home (AUTH-WEB-05)
- [ ] 08-05-PLAN.md — Create custom artisan command for admin user creation (AUTH-WEB-04)
- [ ] 08-06-PLAN.md — Customize login page with AgencySync branding and indigo color scheme (AUTH-WEB-04)

---

### Phase 9: Data Flows, Caching & Operations

**Goal:** Agency admins can export data to spreadsheets, system uses web caching for performance, and server operations are well-documented

**Depends on:** Phase 8 (Hybrid Authentication)

**Requirements:** DATAFLOW-01, DATAFLOW-02, DATAFLOW-03, CACHE-01, CACHE-02, CACHE-03, OPS-01, OPS-02, OPS-03

**Success Criteria** (what must be TRUE):
1. Sync logs can be exported to CSV with tenant, timestamps, and status
2. Product catalog can be exported to CSV/Excel for offline analysis
3. Dashboard metrics are cached for 5 minutes using Redis
4. Tenant list is cached using Cache::remember()
5. Cache invalidates automatically when data updates
6. Server logging documentation exists for Nginx, Laravel, and Supervisor
7. Documentation includes log file locations and common troubleshooting
8. DOITSUYA criteria met: "Data flows (CSV/XML/JSON)" and "Web caching strategies"

**Plans:** 5 plans

- [ ] 09-00-EXPORT-PLAN.md — Wave 0: Create test stubs for data export features (DATAFLOW-01, DATAFLOW-02, DATAFLOW-03)
- [ ] 09-00-CACHE-PLAN.md — Wave 0: Create test stubs for caching features (CACHE-01, CACHE-02, CACHE-03)
- [ ] 09-01-PLAN.md — CSV/Excel export functionality for Sync Logs and Product Catalog (DATAFLOW-01, DATAFLOW-02, DATAFLOW-03)
- [ ] 09-02-PLAN.md — Redis web caching for Dashboard Metrics and Tenant List (CACHE-01, CACHE-02, CACHE-03)
- [ ] 09-03-PLAN.md — Server logging documentation for Nginx, Laravel, and Supervisor (OPS-01, OPS-02, OPS-03)

---

### Phase 10: CI/CD & Testing

**Goal:** System has automated deployment pipeline with comprehensive test coverage

**Depends on:** Phase 9 (Data Flows, Caching & Operations)

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

**Plans:** 3/3 plans complete

- [ ] 10-00-PLAN.md — Wave 0: Create test stubs for deployment script functionality (CICD-04, CICD-05, CICD-06, CICD-07)
- [ ] 10-01-PLAN.md — GitHub Actions CI workflow with PHPUnit testing and 70% coverage enforcement (CICD-01, CICD-02, TEST-04, TEST-05)
- [ ] 10-02-PLAN.md — SSH deployment workflow with deployment script and health check endpoint (CICD-03, CICD-04, CICD-05, CICD-06, CICD-07)

---

### Phase 11: Interactive API Documentation

**Goal:** Beautiful, interactive API documentation that demonstrates API-first backend development capabilities to employers

**Depends on:** Phase 10 (CI/CD & Testing)

**Requirements:** APIDOCS-01, APIDOCS-02, APIDOCS-03, APIDOCS-04, APIDOCS-05

**Success Criteria** (what must be TRUE):
1. Laravel Scribe package installed and configured
2. API documentation auto-generated from Laravel docblocks and route definitions
3. Documentation accessible at `/docs` endpoint with interactive examples
4. All API endpoints documented with request/response examples
5. Authentication methods clearly documented (Sanctum tokens)
6. Example requests with curl commands for each endpoint
7. Response schemas and validation rules documented
8. DOITSUYA criteria met: "API-first backend system" with visible, consumable API
9. Portfolio-ready: employer can see full API capabilities without reading code

**Plans:** 5 plans

- [ ] 11-00-PLAN.md — Wave 0: Create test stubs for API documentation (APIDOCS-01, APIDOCS-02, APIDOCS-03, APIDOCS-04, APIDOCS-05)
- [ ] 11-01-PLAN.md — Install and configure Laravel Scribe (APIDOCS-01, APIDOCS-02)
- [ ] 11-02-PLAN.md — Add comprehensive docblock annotations to all controllers (APIDOCS-03, APIDOCS-04, APIDOCS-05)
- [ ] 11-03-PLAN.md — Regenerate documentation and convert tests to real assertions (APIDOCS-01, APIDOCS-02, APIDOCS-03, APIDOCS-04, APIDOCS-05)
- [ ] 11-04-PLAN.md — Integrate documentation generation with deployment (APIDOCS-01, APIDOCS-02)

---

### Phase 12: Deep-Dive Audit Logs

**Goal:** Enhanced sync logs with detailed error information showing production debugging capabilities

**Depends on:** Phase 11 (Interactive API Documentation)

**Requirements:** AUDIT-01, AUDIT-02, AUDIT-03, AUDIT-04, AUDIT-05

**Success Criteria** (what must be TRUE):
1. Sync Logs table has "View Details" button/modal for each row
2. Failed syncs display raw JSON error payloads from external APIs
3. Laravel stack traces captured and displayed for internal errors
4. Error details include timestamps, error codes, and full context
5. Modal displays error information in formatted, readable JSON
6. Rate limiting errors from Shopify/Shopware APIs clearly shown
7. Success syncs show detailed response data (items processed, duration)
8. DOITSUYA criteria met: "Improving performance, stability, and maintainability" with debugging focus
9. Portfolio-ready: demonstrates production-ready error handling and debugging mindset

**Plans:** TBD

---

## Progress Tracking

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
