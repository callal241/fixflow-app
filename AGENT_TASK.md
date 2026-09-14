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

## How to operate
- Rebuild after code changes: `docker compose build && docker compose up -d`
  (vite frontend is prebuilt into the image; a rebuild is required for JS changes).
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
