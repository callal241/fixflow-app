# AGENT_TASK — FixFlow POS for electronics repair shop

## Assignment
1. Get the base system (fork of webceyhan/fixflow-app, owned by callal241) running.
2. Serve a working preview on Tailscale (done — see below).
3. Then: modify the app to fit the business — fixing AND selling all kinds of
   electronics and odd items (not just device repair). Modifications start when
   the user requests them.

## Repo / workspace
- Repo: github.com/callal241/fixflow-app (cloned at workspace root, on `main`)
- Stack: Laravel 12 + Inertia + Vue 3 + TS + Tailwind 4; sqlite (default)
- Business docs live in `docs/` (data-models, business-rules, etc.)

## Done (2026-08-17)
- [x] Cloned repo (main) into workspace.
- [x] Built Docker runtime: `Dockerfile` (php:8.4-alpine, frontend prebuilt via
      node:22-alpine stage, sqlite), `docker/entrypoint.sh` (key:generate +
      migrate --seed on first boot), `docker-compose.yml` (port 8790, db volume),
      `.dockerignore`.
- [x] Image `fixflow-app:latest` builds clean; container `fixflow-app` up.
- [x] Seeded demo data; login verified: admin@demo.com / password
      (also manager@demo.com, technician@demo.com, same password).
- [x] Preview on Tailscale: **https://remote-vs-1.tail2256b4.ts.net/**
      (tailscale serve --bg 127.0.0.1:8790; tailnet-only, valid TLS).
      Local: http://127.0.0.1:8790/

## Done — business onboarding (2026-08-17)
- [x] Migration `2026_08_17_000001_create_businesses_table.php` (businesses
      table + `users.business_id` FK).
- [x] `app/Models/Business.php` (`users()` HasMany, `admin()` HasOne role filter);
      `User` gets `business_id` fillable + `business()` BelongsTo.
- [x] `app/Http/Controllers/Auth/BusinessRegistrationController.php` (validates,
      creates Business + linked admin User, auto-login, redirect to /dashboard).
- [x] Routes `GET|POST register/business` (guest) in `routes/auth.php`.
- [x] `resources/js/pages/auth/RegisterBusiness.vue` 3-step wizard
      (business info / admin account / review); CTA added to `Welcome.vue`.
- [x] Verified end-to-end: GET /register/business = 200; POST = 302 -> /dashboard;
      rows created: business #1 "Acme Electronics Repair", admin user
      owner@acme.example (role=admin, business_id=1); admin login works;
      seeded data intact (108 customers/devices); Tailscale serves page 200.
- [x] Fixed Tailscale mixed-content: app now trusts the proxy
      (`bootstrap/app.php` `trustProxies(at: ['127.0.0.1','172.16.0.0/12'])`),
      so over HTTPS all asset/redirect URLs are https://, and plain local
      http://127.0.0.1:8790 access still works unchanged.

## Docker root causes FIXED (critical — read before rebuilding)
1. `php artisan serve` spawns the built-in web server WITHOUT inheriting Docker
   `ENV` vars. The live `php -S` worker only saw `APP_ENV` +
   `PHP_CLI_SERVER_WORKERS`. So env-only `DB_DATABASE` was invisible to HTTP
   requests (they fell back to config default `/app/database/database.sqlite`).
   Fix: entrypoint pins `DB_DATABASE=/data/database.sqlite` into `.env` (which
   Laravel always reads) on every boot.
2. The DB named volume was mounted over `/app/database`, which SHADOWED the
   image's migrations dir — new migrations shipped in a rebuilt image were
   invisible to the running container. Fix: persist the DB at a dedicated
   `/data` mount (only the .sqlite file persists); migrations always come from
   the image. Entrypoint also runs `config:clear` on boot (stale cached config).
3. The workspace lives under OneDrive (cloud reparse-point placeholders), so
   `docker build` from the workspace can miss files. Fix: build from a plain
   mirror at `C:\fixflow-src` (refresh with robocopy before each build).
   Rebuild command used:
   `robocopy "<workspace>" C:\fixflow-src /E /XD node_modules vendor .git
   /XF *.sqlite build.log` then
   `docker build -t fixflow-app:latest C:\fixflow-src && docker compose up -d`

## How to operate
- Rebuild after code changes (MUST use the mirror — OneDrive placeholders break
  Docker's context walk):
  1. `robocopy "<workspace>" C:\fixflow-src /E /XD node_modules vendor .git /XF *.sqlite build.log`
  2. `docker build -t fixflow-app:latest C:\fixflow-src`
  3. `docker compose up -d`
  (vite frontend is prebuilt into the image; a rebuild is required for JS changes)
- Reset demo data: `docker compose down && docker volume rm workrepairpossystem_fixflow-db && docker compose up -d`
- Logs: `docker compose logs -f`
- Stop preview: `& "C:\Program Files\Tailscale\tailscale.exe" serve --https=443 off`

## Gotchas learned
- `fakerphp/faker` is a dev dependency — image must be built WITHOUT `--no-dev`
  or seeding crashes (`Class "Faker\Factory" not found`).
- entrypoint.sh must have LF line endings (CRLF breaks `sh`).
- Windows `file_editor` writes CRLF; convert before rebuilds.
- GITHUB_PERSONAL_ACCESS_TOKEN (not GITHUB_TOKEN) works for this repo.

## Next
- [ ] Await user's first modification request for the electronics
      repair + sales workflow.
