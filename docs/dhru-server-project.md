---
name: dhru-server-project
description: "DHRU Server (temporary name) Laravel prepaid-credit service platform — location, logins, design decisions and what is still open"
metadata:
  node_type: memory
  type: project
  originSessionId: 1e78f78d-9ff4-48c1-9ef4-5099ab32bfff
  modified: 2026-11-13T10:48:18.196Z
---

DHRU Server is a Laravel 12 customer platform with prepaid credits and admin-managed services, built 2026-11-13 from the blueprint `C:\Users\Maverick\Downloads\HRU_Server_Project_Blueprint.md`. Environment facts are in [[wamp-environment]].

**Where it is**
- Code: `C:\wamp64\www\GitRepo\dhru-server` (Laravel 12.72.0; the blueprint said 12.69.3).
- Database: MySQL `dhru_server`, root / no password, table prefix `tbl_` set through `DB_PREFIX` in `.env` (models use unprefixed names). Tests use `dhru_server_test`.
- Run: `php artisan serve`, then `/admin/login` (admin) or `/login` and `/register` (customers). `/` is the public home page for visitors (signed-in users are redirected to their dashboard); its texts, popup, flash offer, contact details, logo and the WhatsApp/Telegram buttons are all set in Admin > Settings.
- Seeded admin: username `admin`, password `Admin@12345` (meant to be changed after first sign-in).
- Tests: `php artisan test` runs `tests/Feature/PlatformFlowTest.php` (14 tests, all passing on 2026-11-13).

**Decisions made while building**
- Architecture is Controller → Service → Repository; auth and ownership checks sit in repositories (`AuthRepository`, `findOwnedOrFail`), following SunXFinder.
- One `users` table with `role` (admin/customer) and `status` (active/pending/suspended); one `web` guard, portals gated by the `role:` middleware.
- All balance changes go through `CreditService::move()`: DB transaction, wallet row lock, no negative balance, ledger row with balance before/after. Ledger rows cannot be updated or deleted; refunds are separate rows.
- Orders carry an `idempotency_key` so a double submit is charged once. Price is always read from the database.
- Categories own input fields (`tbl_category_fields`: imei, sn, email, lock_code, text, number); each service stores the field ids it needs in `configuration.field_ids`.
- UI is plain CSS/JS in `public/assets` (no npm build): dark navy theme with orange accent, tables become cards on phones, `partials/page-guide` "How it works" boxes.
- InnoDB is forced in `config/database.php` because this MySQL defaults to MyISAM.

**Still open (as of 2026-11-13)**
- Provider API (added 2026-11-13): services can be linked to a DHRU Fusion API provider (Admin > API providers). The client (`app/Support/DhruFusionClient.php`) was written from the published API standard and is covered by faked-HTTP tests only; it has not been run against a real provider server yet.
- Provider order results need the scheduler (`php artisan schedule:work`, or a task running `schedule:run` every minute). Without it, results are only fetched when a request page is opened or "Check open orders now" is pressed.
- Arabic: all screen text goes through `__()` with `lang/ar.json` (check coverage with a key-extraction pass after adding views). Text typed by the admin (service names, descriptions, field labels) is not translated; announcements have their own Arabic text box.
- Unlinked services are still manual: an admin marks requests processing / completed / failed / cancelled.
- "EMI" was implemented as IMEI (15 digits); the user has not confirmed this reading.
- State and City are text boxes, not dropdowns (no dataset).
- Password-reset mail goes to the log until real mail settings are added to `.env`.
- Running under WAMP Apache at `/GitRepo/dhru-server/public` is untested.
- "DHRU Server" is a temporary project name.
