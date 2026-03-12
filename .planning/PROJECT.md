# AgencySync

## What This Is

AgencySync is a multi-tenant API-first backend system designed for e-commerce agencies to manage multiple client stores efficiently. The system acts as a centralized control center that enables agencies to monitor client deployment status, perform mass catalog synchronization from e-commerce platforms (Shopify, Shopware), and provide ultra-fast internal product search using Elasticsearch. Built with Laravel 11, PHP 8.2+, MySQL 8.0, Elasticsearch, Redis, and Docker for complete containerization.

## Core Value

E-commerce agencies can reliably manage and synchronize product catalogs across multiple client stores with sub-second search performance and non-blocking background processing.

## Requirements

### Validated

(None yet — ship to validate)

### Active

- [ ] Multi-tenant database architecture with tenant isolation
- [ ] Asynchronous catalog synchronization from Shopify/Shopware APIs
- [ ] Elasticsearch-powered global product search with fuzzy matching
- [ ] Agency admin dashboard for client management and monitoring
- [ ] Background job processing with Supervisor and Redis queues
- [ ] CI/CD pipeline with GitHub Actions
- [ ] Docker containerization for all services
- [ ] API-first architecture with RESTful endpoints

### Out of Scope

- Real-time sync (WebSocket) — Initial release uses polling/periodic sync
- Mobile applications — Web-first, mobile-responsive admin dashboard only
- Multi-user roles within agency — Single admin user for v1
- Payment processing — Not in scope for agency management system
- Client-facing interfaces — This is an agency backend tool only

## Context

**Business Problem:** E-commerce agencies face significant challenges managing multiple client stores: ensuring catalog synchronization doesn't timeout, maintaining server stability, and providing visibility across all deployments. Current solutions either lack multi-tenant capabilities or require manual intervention for routine tasks.

**Target Users:** E-commerce agency developers and operations teams who need to monitor and manage multiple client stores from a single interface.

**Technical Environment:**
- Fresh Laravel 11 installation as foundation
- Modern PHP 8.2+ features (Enums, Readonly properties, Named arguments)
- Containerized infrastructure using Docker Compose
- CI/CD automation via GitHub Actions with SSH deployment

## Constraints

- **Tech Stack**: Laravel 11, PHP 8.2+, MySQL 8.0, Elasticsearch/OpenSearch, Redis, Supervisor, Nginx — Must align with modern PHP ecosystem standards
- **Deployment**: Self-hosted Docker Compose setup — Must be deployable to any machine or cloud VPS
- **Performance**: Search queries must return in sub-second time — Elasticsearch cluster configuration critical
- **Background Processing**: Catalog sync must not block application — Redis queues with Supervisor mandatory
- **Documentation**: All APIs must be documented — Portfolio project requires clear technical communication
- **Testing**: Automated tests required — CI/CD pipeline must include test execution

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Multi-tenant via tenant_id | Single database simpler than multi-database for self-hosted deployment | — Pending |
| Elasticsearch over MySQL full-text | Sub-second search performance required for large catalogs | — Pending |
| Redis Queue for sync jobs | Non-blocking catalog sync critical for UX | — Pending |
| Docker Compose over Kubernetes | Self-hosted deployment, simpler for portfolio demo | — Pending |
| Blade + Alpine.js over SPA | Demonstrates modern PHP templating with lightweight JS | — Pending |

---
*Last updated: 2026-03-13 after initialization*
