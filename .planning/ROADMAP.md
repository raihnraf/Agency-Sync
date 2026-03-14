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

**Plans:** 7 plans

- [ ] 08-00-PLAN.md — Wave 0: Create test stubs for hybrid authentication (AUTH-WEB-01, AUTH-WEB-02, AUTH-WEB-03, AUTH-WEB-04, AUTH-WEB-05)
- [ ] 08-01-PLAN.md — Install Laravel Breeze (Blade edition) for session-based authentication scaffolding (AUTH-WEB-01)
- [ ] 08-02-PLAN.md — Configure web routes for session auth and API routes for Sanctum token auth (AUTH-WEB-02, AUTH-WEB-03)
- [ ] 08-03-PLAN.md — Remove registration routes to prevent self-registration (AUTH-WEB-04)
- [ ] 08-04-PLAN.md — Configure logout redirect to home page (/) instead of /home (AUTH-WEB-05)
- [ ] 08-05-PLAN.md — Create custom artisan command for admin user creation (AUTH-WEB-04)
- [ ] 08-06-PLAN.md — Customize login page with AgencySync branding and indigo color scheme (AUTH-WEB-04)

---

### Phase 9: CI/CD & Testing

**Goal:** System has automated deployment pipeline with comprehensive test coverage

**Depends on:** Phase 8 (Hybrid Authentication)

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

**Plans:** 3 plans

- [ ] 09-00-PLAN.md — Wave 0: Create test stubs for deployment script functionality (CICD-04, CICD-05, CICD-06, CICD-07)
- [ ] 09-01-PLAN.md — GitHub Actions CI workflow with PHPUnit testing and 70% coverage enforcement (CICD-01, CICD-02, TEST-04, TEST-05)
- [ ] 09-02-PLAN.md — SSH deployment workflow with deployment script and health check endpoint (CICD-03, CICD-04, CICD-05, CICD-06, CICD-07)

---

## Progress Tracking

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
