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
