### Phase 14: Critical Frontend Fixes

**Goal:** Fix 2 broken user-facing flows that block core features (product search and sync trigger)

**Depends on:** Phase 13 (Technical Debt Refactoring)

**Type:** Gap Closure Phase (Critical - Blocks Milestone)

**Requirements:** SEARCH-01, SEARCH-07, SYNC-01, UI-05, UI-07

**Gap Closure:** Closes critical broken flows and verification gaps from v1.0 milestone audit

**Success Criteria** (what must be TRUE):
1. Product search UI calls correct `/search` endpoint (not `/products`)
2. Sync trigger button calls valid route (POST /api/v1/sync/dispatch with tenant_id in body)
3. No undefined variable bugs in JavaScript (ReferenceError-free)
4. All test files have real assertions (GREEN phase)
5. Users can search products within client catalogs
6. Users can trigger sync operations for client stores
7. Integration verified: Frontend → API route connections working
8. Milestone-ready: No broken user-facing flows

**Plans:** 6/6 plans complete

- [x] 14-00-PLAN.md — Wave 0: Create test stubs for frontend API integration (SEARCH-01, SEARCH-07, SYNC-01, UI-05, UI-07) ✅
- [x] 14-01-PLAN.md — Fix product search endpoint in frontend (SEARCH-01, SEARCH-07, UI-07) ✅
- [x] 14-02-PLAN.md — Fix sync trigger endpoint in frontend (SYNC-01, UI-05) ✅
- [ ] 14-03-PLAN.md — Gap closure: Fix undefined variable bug in sync trigger (line 189) (SYNC-01, UI-05)
- [ ] 14-04-PLAN.md — Gap closure: Fix undefined variable bug in export products (line 809) (UI-05)
- [ ] 14-05-PLAN.md — Gap closure: Implement real test assertions in 3 placeholder files (SEARCH-01, SEARCH-07, SYNC-01, UI-05, UI-07)


### Phase 15: Complete Dashboard Integrations - Route protection on dashboard endpoints and real-time sync status polling mechanism for production readiness

**Goal:** Complete dashboard integrations for production readiness - secure dashboard routes with authentication middleware and implement real-time sync status polling for tenant list view

**Depends on:** Phase 14

**Type:** Standard Phase (Production Readiness)

**Requirements:** AUTH-04, SYNC-06, UI-06

**Gap Closure:** Closes production readiness gaps for dashboard security and real-time UX

**Success Criteria** (what must be TRUE):
1. Dashboard web routes protected with authentication middleware (unauthenticated users redirected to login)
2. API endpoint exists to fetch latest sync status for a tenant (/api/v1/sync/status/{tenantId})
3. Sync status endpoint validates tenant ownership (prevents cross-tenant enumeration)
4. Tenant list view displays sync status for each tenant
5. Sync status updates in real-time via 2-second polling mechanism
6. Polling stops automatically when sync completes or fails
7. Polling intervals cleaned up on page navigation (prevents memory leaks)
8. All test files have real assertions (GREEN phase)

**Plans:** 5 plans

- [ ] 15-00-PLAN.md — Wave 0: Create test stubs for dashboard integrations (AUTH-04, SYNC-06, UI-06)
- [ ] 15-01-PLAN.md — Verify dashboard route protection (AUTH-04)
- [ ] 15-02-PLAN.md — Create sync status polling endpoint (SYNC-06)
- [ ] 15-03-PLAN.md — Implement tenant list sync status polling (UI-06) - JavaScript + Blade template
- [ ] 15-04-PLAN.md — Test and verify tenant list polling (UI-06) - Tests + Human verification



### Phase 18: Portfolio-Ready Fixes - Dashboard Security & UI Bug Fixes

**Goal:** Fix visible UI bugs and basic security gaps for DOITSUYA job application portfolio

**Depends on:** Phase 14

**Type:** Gap Closure Phase (Portfolio-Ready - Visible Fixes Only)

**Requirements:** AUTH-04, SYNC-06, UI-06

**Gap Closure:** Closes visible UI bugs and basic security gaps from v1.0 milestone audit

**Success Criteria** (what must be TRUE):
1. Dashboard web routes protected with authentication middleware (security gap closed)
2. Sync status API route mismatch fixed in frontend JavaScript (visible UI bug fixed)
3. Sync status displays correctly in tenant list view (visible UI bug fixed)
4. Simple refresh mechanism or fixed API endpoint (no complex real-time polling needed)

**Plans:** 2/3 plans executed

- [ ] 18-01-PLAN.md — Fix sync status API route mismatch in dashboard.js line 150 (SYNC-06, UI-06)
- [ ] 18-02-PLAN.md — Verify dashboard route authentication middleware (AUTH-04)
- [ ] 18-03-PLAN.md — Add sync status display to tenant list view (UI-06)

**Notes:**
- Focus on visible fixes only (what recruiters see in demo)
- Skip complex real-time polling (use simple page load fetch)
- Skip documentation/verification files (not valuable for recruiters)
- Skip TENANT-05 stub validation (add TODO comment, acceptable for portfolio)

