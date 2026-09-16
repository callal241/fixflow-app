# AGENT_TASK — Finish FixFlow Repair (full spec, 2026-08-17)

## Assignment (authoritative)
Take FixFlow from its current state to a production-ready repair-shop POS + operations
platform per the 57-section spec (customer intake → device ID → ticket → condition docs →
repair selection → estimate → approval (pricing snapshot) → part reserve/order → repair →
testing → invoice → payment → receipt → close; then suppliers, search, portal, automations,
reporting, Device Bridge, RBAC/audit, multi-location, API/webhooks).
EXISTING app: audit first, preserve working functionality + visual identity, incremental
improvement. No dead buttons, no fake data, no frontend-only features, server-side
validation + permissions. Tenant isolation is critical.

## Prior completed state (verified, branch `docker-preview`)
- Laravel 12 / Inertia / Vue3 / TS / Tailwind(shadcn-style) / SQLite / Docker, PHP 8.4
- 8 business-scoped core models + `BelongsToBusiness` trait (business_id nullable,
  forBusiness() scope, auto-stamp). Business = tenant unit.
- Catalog: Category + Product (active/lowStock scopes). Dashboard, Products (index/create),
  Tickets (index/show), product-sales lines on orders (OrderObserver stock consume/restore),
  invoices aggregate billable orders/tasks.
- Demo seed: biz "FixFlow Demo Shop" (admin@demo.com / password) + "Second Bird"
  (manager@secondbird.test / password) proving tenant isolation. users=19, businesses=2,
  products=86, tickets=121, customers=111, invoices=118, transactions=61.
- Test env fixed & green: 329 passed / 0 failed / 15 design-skips (1250 assertions).
  DO NOT bake ENV back into Dockerfile (reintroduces live-DB shadowing).
- reka-ui Select components exist (dark mode); use them for dropdowns, not native select.
- lucide-vue-next ^0.468.0. No `Table`/`Textarea` UI components (plain markup).
- UI: AppLayout + AppSidebar (Dashboard, Tickets, Products, Settings, Auth).

## Environment / workflow
- Repo callal241/fixflow-app, local `C:\Users\User\OneDrive\Documents\work repair pos system`
  on `docker-preview` (pushed). Work in this dir; push on request.
- Container `fixflow-app`: app /app, DB volume `workrepairpossystem_fixflow-db`:/data,
  HTTP http://127.0.0.1:8790 (port 80 in container).
- Tests: `docker exec fixflow-app sh -c "cd /app && php artisan test --compact"`
- Rebuild: `docker compose build` (Start-Job → C:\fixflow-src\buildN.log; poll) →
  `docker rm -f fixflow-app` → `docker compose up -d` → verify. Compose warnings to stderr
  are harmless (NativeCommandError).
- PowerShell: no heredocs (file_editor + docker cp), no &&, chain with ;, timeout<=120s,
  empty output = finished. UNIX grep/find → `wsl -e bash -lc '...'` on /mnt/c/... paths.
- HTTP testing: curl INSIDE container (127.0.0.1:80); XSRF via cookie from /login,
  send as X-XSRF-TOKEN (URL-decoded); re-read cookie after login (rotates).
- Long commands: Start-Job + log file + poll. Never block > ~60s.

## Implementation map — FILL DURING AUDIT (Milestone 0)
Statuses: WORKING / PARTIAL / BROKEN / PLACEHOLDER / MISSING
Sections 1-57 of spec. Milestone 1 = full repair lifecycle with real persistent data.

## Checklist
- [x] 0-Audit: backend (controllers/models/migrations/routes/middleware)
- [x] 0-Audit: frontend (pages/components/stores/routes/sidebar)
- [x] 0-Audit: DB schema + enums + seeders
- [x] 0-Map: classify all spec sections (recorded in "Audit findings" below)
- [x] Phase A: schema — ticket_number (FF-xxxxx auto + backfill), device fields
      (model_number/imei/color/storage/carrier), ticket internal_notes + intake_type.
      Verified: fresh DB chain + backfill (121 tickets numbered), live DB reseeded,
      suite green (330 passed / 0 failed / 15 skips).
- [ ] 1a: Customers (create/edit/list/detail, business accounts, multiple devices/contacts)
- [ ] 1b: Devices (manufacturer/model/serial/IMEI/etc., device catalog hierarchy)
- [ ] 1c: Ticket intake (number, fields, photos, notes, authorization, status)
- [ ] 1d: Ticket workflow (status transitions, notes/timeline, tasks/repairs, parts)
- [x] 1e: Estimates/quotes (options, approve, pricing snapshot)
      DONE (2026-09-16, commit f51a42b): invoice IS the estimate (Draft);
      invoices.approved_at + Invoice::approve() re-syncs totals+status in a
      txn (approved+unpaid => Sent); POST tickets/{ticket}/invoice/approve
      (-> InvoiceController::update, whitelisted name) w/ tenant 403 +
      draft-only + billable-work guards; clears billable tasks' approval gate;
      Tickets/Show.vue "Approve estimate" action + Approved badge; 7 tests.
      (options / multiple quote revisions = future enhancement)
- [ ] 1f: Inventory (stock, reserved/available, reorder, receiving)
- [ ] 1g: POS/checkout (payments, deposits, refunds, receipts)
- [ ] 1h: Invoices (from ticket, pay, status)
- [ ] 1i: Testing/checklists (pre/post repair)
- [ ] 1j: End-to-end verification with real data + tests
- [ ] 2: Global search + command palette (Ctrl/Cmd+K)
- [ ] 3: Supplier provider architecture + MobileSentrix/PhoneLCD/iFixit
- [ ] 4: Parts from ticket (Find Parts), purchase orders, receiving
- [ ] 5: Communications + customer portal
- [ ] 6: Reporting + dashboards (technician/store)
- [ ] 7: Automations, notifications
- [ ] 8: RBAC + audit log + passcode sensitivity
- [ ] 9: Multi-location, appointments, mail-in
- [ ] 10: Device Bridge
- [ ] 11: API/webhooks, accounting adapters
- [ ] 12: Full end-to-end QA

## Current step
DONE (2026-09-16): Estimate & Approval (1e) — commit f51a42b. Customer go-ahead
recorded via invoices.approved_at; approved+unpaid invoice surfaces as Sent;
POST tickets/{ticket}/invoice/approve (-> InvoiceController::update); billable
tasks' approval gate cleared on approve; Tickets/Show.vue "Approve estimate"
action + Approved badge; 7 tests; suite 358 pass / 0 fail; Vite build green.

NEXT (1i): Testing/checklists — pre-repair (condition at intake) + post-repair
(QC sign-off) attached to the ticket, so a repair is documented before/after.
Pattern source: TaskController (line-item CRUD on a ticket) + the new
estimate/approve flow for the "clear the gate" behavior. Keep tenant 403 guards
+ arch preset (controller public methods limited to the whitelisted verbs).

## Audit findings (2026-08-17, verified against repo + live DB)

### BACKEND — WORKING (solid foundation, 329 tests pin the contract)
- 12 models, all business-scoped via BelongsToBusiness (business_id, forBusiness scope,
  auto-stamp). Enums w/ HasNext + HasProgress (pending/complete cases): TicketStatus
  (new/in_progress/on_hold/resolved/closed), TaskType (10 types), TaskStatus, OrderStatus,
  DeviceStatus (received/on_hold/under_repair/ready/delivered), DeviceType, InvoiceStatus
  (draft/issued/sent/paid/refunded/cancelled), Priority (low..urgent), UserRole
  (admin/manager/technician), TransactionMethod/Type, AdjustmentType/Reason.
- Observer engine (WORKING, tested): TaskObserver + OrderObserver → invoice totals;
  InvoiceObserver → % adjustments; TransactionObserver → paid/refunded + auto status;
  Ticket/Device/Adjustment → rollup counts. Invoice computed props: subtotal, net_amount,
  balance, getComputedStatus.
- Controllers/routes EXIST: Dashboard, Product (index/create/store), Ticket (index/show),
  Order (store/destroy), full Auth suite, Settings (profile/password).
- Factories + seeders for ALL models (states: forCustomer/forDevice/forTicket/billable/…).
- Workflow feature tests define E2E contract: customer→device→ticket→invoice→tasks→
  orders→adjustment→payment→paid.

### FRONTEND — WORKING (good kit, thin app layer)
- Full shadcn/reka-ui kit: Button, Card, Input, Label, Select, Checkbox, Dialog,
  DropdownMenu, Sheet, Tooltip, Avatar, Breadcrumb, Sidebar, Separator, Skeleton,
  Collapsible, NavigationMenu. AppLayout + AppSidebar + breadcrumbs + Heading.
- Inertia 2 + Ziggy (route() in Vue) + useForm. Tailwind 4, dark mode. lucide icons.
- Pages EXIST: Dashboard, Welcome, 7 auth, Products/Index+Create, Tickets/Index+Show,
  Settings x3. Sidebar: Dashboard, Products, Tickets.

### THE GAP — application layer (Milestone 1 blockers)
- MISSING: Customers CRUD (no controller/routes/pages).
- MISSING: Devices CRUD.
- MISSING: Ticket create (intake) + update (status/assignee/priority/due/notes).
- MISSING: Tasks UI (model+enum+observer all exist; zero UI/routes).
- MISSING: Invoice UI (create-from-ticket, adjustments, payments, refunds, receipt).
- MISSING: free-form parts (orders without product_id) in UI (product sale only).
- MISSING: ticket number (spec §1) — tickets have only autoincrement id.
- MISSING: device detail fields (imei/color/storage/carrier — spec §1/§2).
- MISSING: internal vs customer-visible notes.
- MISSING (later milestones): global search, RBAC enforcement, audit log, photos,
  labels/QR, estimates-as-portal, suppliers, comms, automations, reports, device bridge,
  multi-location, API/webhooks.

### DECISIONS (documented per spec §55)
- Keep stack (Laravel/Inertia/Vue/SQLite/Docker) — spec §47. Build application layer on
  existing models/observers; do NOT rewrite engine.
- Ticket number: `FF-` + 5-digit zero-padded global id (set on creating if empty) —
  stable, searchable, tenant-unique.
- Estimate = Invoice(Draft) created from ticket; approval = invoice sent + task approvals.
  (Matches existing architecture; InvoiceStatus already models the lifecycle.)
- v1 device fields: imei, color, storage, carrier (rest later). Photos/QR/suppliers
  post-milestone.
- Roles: keep enum; add server-side role guards on sensitive routes (admin settings)
  when building; full RBAC matrix post-milestone.

