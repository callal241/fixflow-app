# Project memory — FixFlow POS (work repair pos system)

Durable facts about this repo + its Docker runtime. Verified 2026-08-17 (session dates
on this machine may be off; facts were verified by running the commands).

## Docker runtime — root causes (both cost a long debugging session)

1. **`php artisan serve` does NOT pass Docker `ENV` vars to the live `php -S` HTTP
   worker.** Only `APP_ENV` and `PHP_CLI_SERVER_WORKERS` reach it. Verified by dumping
   `/proc/<pid>/environ` of both processes: parent had `DB_DATABASE=/data/...`,
   the serving child had NO `DB_*` at all. Consequence: any setting that must hold at
   HTTP time (DB path, APP_URL, etc.) must go in `.env` inside the image, not just
   Dockerfile `ENV`. `docker/entrypoint.sh` pins `DB_DATABASE` into `.env` on boot.
2. **A named volume mounted over a source-code dir (e.g. `fixflow-db:/app/database`)
   SHADOWS that dir from the image** — new migrations in rebuilt images become
   invisible, while `docker run` on the image shows them. DB now persists at
   `/data` (only the .sqlite file), so migrations always come from the image.
3. **OneDrive cloud placeholders break `docker build` context** from the workspace:
   files can silently go missing from the image. Always build from the plain mirror:
   robocopy workspace → `C:\fixflow-src` (exclude node_modules, vendor, .git, *.sqlite)
   → `docker build -t fixflow-app:latest C:\fixflow-src` → `docker compose up -d`.
   `docker compose build` from the workspace is not reliable here.
4. Entrypoint also runs `php artisan config:clear` on boot — stale cached config
   (`bootstrap/cache/config.php`) outlived a DB-path change and kept the app on the
   old path even after fixes.

## Environment quirks (this machine)

- PowerShell mangles multi-line `docker exec sh -c '...'` and here-strings badly:
  the embedded `$var` / backticks get eaten or the command never executes (shell
  echoes `>>` and appears to hang). Write the script to a file
  (`Set-Content ... -Encoding ascii`), `docker cp` it in, `sh /tmp/script.sh`.
- `php artisan tinker` HANGS (interactive REPL, no TTY) inside `docker exec`.
  For one-shot queries use `docker exec fixflow-app sqlite3 /data/database.sqlite "..."`
  instead — it works and is fast.
- `grep`/`sed` etc. don't exist in the PowerShell session; use the container's
  `sh -c` for those, or WSL with /mnt/c/... paths.
- GITHUB_PERSONAL_ACCESS_TOKEN (not GITHUB_TOKEN) works for callal241/fixflow-app.

## App state

- Branch `docker-preview` (local). Remote `origin` has only `main`.
  Nothing pushed beyond `82ac828` + business onboarding `4dc1cd8` (local only).
- Preview: Tailscale serve → https://remote-vs-1.tail2256b4.ts.net/ → 127.0.0.1:8790.
- DB: SQLite at named volume `workrepairpossystem_fixflow-db`, mounted at `/data`.
  Reset: `docker compose down && docker volume rm workrepairpossystem_fixflow-db && up -d`.
- Business onboarding lives at `/register/business` (guest), creates
  `businesses` row + linked admin `users` row, auto-login. Demo rows exist:
  business #1 "Acme Electronics Repair", owner@acme.example / password123.
- Seeders: admin/manager/technician@demo.com + 108 customers/devices (faker).
