# Pots & Planters 2.0 — Infrastructure Specification

**Document:** `03-infrastructure-spec.md`  
**Stage:** 1 of 4 — Infrastructure foundation  
**Status:** Ready for Codex execution  
**Date:** 2026-10-17

## 1. Context and objective

We are rebuilding potsandplanters.ae from OpenCart into a B2B-first website. The agreed implementation sequence is:

1. Infrastructure (this document).
2. Full Next.js frontend based on the old theme, with approved UX improvements and mock data.
3. Laravel API, business models, Filament administration, data migration and integrations.
4. SEO, structured data, redirects, performance and stakeholder review.

**This stage must deliver a reproducible monorepo and working containers only.** Do not implement products, categories, catalogues, inquiries, Telegram, checkout, content pages or SEO features now.

## 2. Required technology

- **Backend:** Laravel **13**, PHP **8.4**, Composer, PHP-FPM.
- **Admin:** Filament, latest stable release **compatible with Laravel 13 and PHP 8.4**. Confirm compatibility before installation; do not force dependencies.
- **Frontend:** Next.js (current compatible stable release), TypeScript, React, Mantine UI (user sometimes spells it “Mantime”). Use App Router.
- **Runtime:** supported Node.js LTS compatible with selected Next.js release; pin the major version in Docker and document the actual versions installed.
- **Database:** MariaDB, pinned supported stable version.
- **Cache and queues:** Redis; Laravel Horizon as a separate service.
- **Proxy:** nginx for PHP-FPM/API/admin, with a single public-facing entrypoint or documented Dokploy routing. Avoid an unnecessarily complex proxy chain.
- **Orchestration:** Docker Compose, single Git repository, deployable through Dokploy as a Compose application.

Before implementation, check official package requirements for the versions selected and record them in the README. If a requested version is incompatible, stop and report the exact conflict instead of silently downgrading Laravel or changing the stack.

## 3. Repository structure

```text
potsandplanters/
├── backend/                    # Laravel app and Filament
├── frontend/                   # Next.js App Router + Mantine
├── docker/
│   ├── backend/Dockerfile
│   ├── frontend/Dockerfile
│   └── nginx/default.conf
├── compose.yaml                # Production-like base
├── compose.dev.yaml            # Local development overrides
├── .env.example                # Compose/runtime variable template; no secrets
├── .gitignore
└── README.md
```

Reasonable additional config files are allowed. Do not create a generic monorepo framework or add Nx/Turborepo unless there is a demonstrated need.

## 4. Compose services and behavior

| Service | Responsibility | Public exposure |
|---|---|---|
| `frontend` | Next.js SSR / standalone production server | Via proxy/router only |
| `backend` | Laravel PHP-FPM | Internal only |
| `nginx` | Laravel HTTP endpoint for API and Filament | Via proxy/router only |
| `db` | MariaDB persistent data | Internal only |
| `redis` | Cache/queues | Internal only |
| `horizon` | Laravel Horizon worker process | Internal only |
| `scheduler` | Laravel scheduled task runner | Internal only |

Choose and document a clear routing arrangement for local and Dokploy environments. Preferred public routing is **one frontend host** and a backend path for `/api/*` and `/admin/*`, including Filament assets, Livewire endpoints and authentication; alternatively use an explicit backend subdomain if path routing causes unnecessary fragility. Do not assume that forwarding `/admin` alone is sufficient for Filament. All redirects, cookies, session and asset URLs must work end-to-end.

- Development: bind only the intended local HTTP entrypoint(s) to host ports; hot reload works for both applications where appropriate.
- Production: multi-stage Docker builds, Next.js `output: 'standalone'`, no `next dev`, no source bind mounts, no development dependencies required at runtime.
- Use named persistent volumes for MariaDB and Laravel user-generated storage. Define correct permissions for `storage` and `bootstrap/cache`.
- Use internal Docker DNS/service names rather than `localhost` for inter-container communication.
- Add meaningful healthchecks and startup dependencies where practical. Do not equate `container started` with `application ready`.
- Handle application startup, migrations and restarts safely; avoid concurrent migrations from multiple services.
- The queue worker and scheduler use the same Laravel code/config as the backend; no separate code copies or divergent application keys.

## 5. Backend initialization

1. Create a clean Laravel 13 application in `backend/`.
2. Configure MariaDB connectivity and Redis for cache/queues; use environment variables and appropriate PHP extensions.
3. Install Filament with an admin panel at `/admin`; create a documented command for creating the first admin account **interactively** (do not commit credentials or auto-create a known password).
4. Install/configure Horizon; ensure a dedicated `horizon` Compose service starts successfully and can reach Redis. Restrict Horizon dashboard access to authorized users in all environments.
5. Configure scheduler as its own service or documented equivalent, ensuring only one scheduler runs per deployment.
6. Add a minimal `/api/health` endpoint returning a machine-readable success response. It should be lightweight; if used for readiness, document which dependencies it checks.
7. Do not generate business-domain migrations, Filament resources, API endpoints or example product records.

## 6. Frontend initialization

1. Create Next.js App Router app in `frontend/` with TypeScript and Mantine configured correctly for SSR and styles.
2. Add a minimal branded placeholder home page stating that the infrastructure is ready; this is **not** the final Pots & Planters UI.
3. Include a small, isolated API client configuration for calling Laravel without hardcoding container addresses into browser bundles.
4. Ensure local development hot reload works and the production build completes successfully.
5. Keep frontend dependency versions pinned by a committed lockfile.
6. Do not implement the catalogue, search, product pages, shopping cart, PDF forms or floating buttons in this stage.

## 7. Configuration and security

- Provide a complete `.env.example` describing all required values, including app URL(s), MariaDB, Redis, Laravel key, session/cookie settings and proxy-related variables.
- Do not commit `.env`, secrets, credentials, generated keys or database dumps.
- Laravel must run with `APP_DEBUG=false` in production; frontend and backend should use production runtime modes.
- Database and Redis ports must not be publicly published in production.
- Admin panel requires authentication. Document how to create the initial admin securely.
- Document trusted proxy/HTTPS handling for Dokploy, so generated URLs and cookies use HTTPS.
- Do not set global `noindex` on production by accident. Staging must be private (prefer authentication); SEO implementation belongs to stage 4.
- Implement reasonable log output to stdout/stderr for container diagnosis; document where Laravel logs are available.
- Ensure the repo works without credentials to external services (Telegram, SMTP, third-party APIs) at this stage.

## 8. Development workflow

Provide tested commands for:

- Initial environment setup (`cp .env.example .env` plus documented Laravel app-key generation).
- Build/start development containers (`docker compose -f compose.yaml -f compose.dev.yaml up -d --build`, or a clearly documented equivalent).
- Build/start production-like containers (`docker compose up -d --build`).
- Run Laravel migrations.
- Create a Filament administrator interactively.
- Access backend and frontend shells/logs.
- Stop/restart services without destroying persistent data.
- Rebuild only one changed service.

Local developer should not need globally installed PHP, Composer, Node or npm to boot the project after Docker is available.

## 9. Dokploy deployment readiness

- Ensure Compose uses portable paths and documented environment variables; do not hardcode a server IP or domain.
- Document how to deploy this repo as a Dokploy Compose application and map the external domain(s) to the appropriate HTTP service(s).
- Explain where TLS terminates and how Laravel handles forwarded HTTPS headers.
- Ensure persistent volumes survive redeployments.
- Ensure production startup does not depend on local development bind mounts.
- Document release-time migrations, safe application restarts and rollback limitations. Do not run destructive commands automatically.
- A real Dokploy deployment is **not** required for acceptance unless deployment access is available; local production-like Compose must work.

## 10. Acceptance criteria (all required)

1. `docker compose config` validates the base configuration, and the dev override validates separately.
2. `docker compose up -d --build` starts all required production-like services without crash loops.
3. Frontend homepage responds HTTP 200 and visibly renders Mantine styling.
4. Laravel `/api/health` responds HTTP 200 with JSON.
5. Filament `/admin` loads and redirects unauthenticated users to its login page; authorized admin can log in after account creation.
6. Laravel can connect to MariaDB and run its initial migrations.
7. Laravel can connect to Redis; Horizon runs and consumes the configured queue.
8. Scheduler service is running and does not create duplicate schedulers.
9. Next.js production build is successful and uses standalone mode.
10. A dev-mode file edit is reflected by hot reload without rebuilding the entire stack.
11. MariaDB and Redis are not reachable through publicly published production ports.
12. Containers restart successfully and persistent MariaDB data survives restart/recreate.
13. README describes local development, production-like Compose, Dokploy deployment and diagnostics.
14. No business functionality from stages 2–4 is implemented prematurely.

If Docker cannot be run in the Codex environment, perform all possible static/build checks, explicitly list unverified runtime checks, and **do not claim full acceptance**.

## 11. Codex execution instructions

Work in small checkpoints, in this order:

- **INF-01:** Inspect repo; create monorepo skeleton and env examples.
- **INF-02:** Bootstrap Laravel 13, PHP Dockerfile, MariaDB, Redis; verify connectivity.
- **INF-03:** Install Filament and Horizon; add scheduler; verify authentication/service startup.
- **INF-04:** Bootstrap Next.js + TypeScript + Mantine; verify SSR and dev/production builds.
- **INF-05:** Wire Compose networks, nginx routing, persistent volumes, healthchecks, production/dev separation.
- **INF-06:** Validate integration and document Dokploy deployment.

At each checkpoint: report files changed, commands run, results, blockers, and next step. Avoid unrelated refactoring. If the repository already contains code, inspect it first and preserve existing work. Ask before deleting or overwriting meaningful files.

**Final response format:** brief summary; chosen versions; architecture/routing decisions; commands executed with pass/fail; list of unverified acceptance criteria; commands for the user to launch and create the admin account.

## 12. Out of scope

No OpenCart migration; no products/categories/catalogues/news/contacts models; no Filament resources beyond a functioning admin panel; no Telegram/email integrations; no PDF downloads; no SEO schema/sitemap/redirects; no frontend redesign or real site pages; no analytics; no external infrastructure provisioning without explicit permission.

## 13. Architecture decisions to preserve for later stages

- B2B-first site; retail-style cart is a product-selection enquiry, not online payment.
- Telegram group receives enquiries; Filament stores backup records only, not a CRM.
- Category pages are individually addressable SEO routes, while category selection feels like a filter.
- Product search must support exact/partial names and exact/partial SKUs.
- Filament will eventually manage categories, products, PDF catalogues, inquiries, page SEO, news, contacts/locations and Telegram/email settings.
- WhatsApp and Catalogues floating actions are mandatory in the later frontend stage.
- Static editorial layout and infrequently changed content remain in code; Filament is not a page builder.
