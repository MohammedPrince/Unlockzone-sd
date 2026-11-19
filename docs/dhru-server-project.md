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
- API clients (added 2026-11-17): the reverse direction, this server as a DHRU Fusion provider. `POST {site}/api/index.php` (`routes/api.php`, `app/Services/DhruApiServer.php`) answers accountinfo, imeiservicelist, placeimeiorder, getimeiorder. Each client is tied to one customer account (`tbl_api_clients`, one per customer): the other server signs in with that customer's username (or email) + an API key, and orders are charged to that customer's wallet through the normal `ServiceRequestService::submit` (marked with `service_requests.api_client_id`). Access is all services or a ticked list (`tbl_api_client_services`); customer suspensions/blocks still apply; optional IP allow-list. Pricing per client: `api_clients.price_adjust` (percent on the site price, -90..+1000) and optional fixed prices per service (`tbl_api_client_prices`, wins over the percent); `ApiClient::priceFor()` is used both for the API price list and for the amount charged (`ServiceRequestService::submit` takes the client). Normal customers keep the site price. Only a SHA-256 of the key is stored: it is shown once after create / "New key". Delete removes API access only (customer, credit and orders stay). Admin UI is the second section on Admin > API providers. Tested by `tests/Feature/ApiClientTest.php`, which points our own `DhruFusionClient` at this API; not yet tried with a third-party DHRU client.
- Admin manual (added 2026-11-17): `/admin/guide` (sidebar: Platform > Admin manual), `resources/views/admin/guide.blade.php`. English and Arabic text live side by side in that file and follow the EN / عربي switch; update both when a feature changes.
- Maintenance mode (added 2026-11-17): Admin > Settings (one-click button at the top, or the "Maintenance mode" card). `app/Http/Middleware/MaintenanceMode.php` (last in the web group, after TrackVisit so visits still count) shows `resources/views/maintenance.blade.php` with HTTP 503 to everyone except signed-in admins; `/admin/*`, `/language/*`, provider callbacks, terms/privacy stay open. The server API (`routes/api.php`) is not affected. This is separate from Laravel's own `php artisan down`.
- VIP prices (added 2026-11-19): Admin > Services > VIP prices (`/admin/vip-prices`, `VipPriceController`). A customer is VIP when `users.is_vip` is on (button on the admin customer profile; not fillable). VIP price = `services.vip_price` when set, else site price × (1 + setting `vip_price_adjust`/100). `Service::priceFor($user)` / `totalFor($qty, $user)` give what a customer pays; the order charge (`ServiceRequestService::submit`), catalog, order page and ticker use it. API clients keep their own pricing (`ApiClient::priceFor`). The page submits only changed price boxes (PHP `max_input_vars`); a missing box means "unchanged". `customer_tag` ("VIP" label) is separate and does not change prices. Tests: `tests/Feature/VipPricingTest.php`.
- Provider order results need the scheduler (`php artisan schedule:work`, or a task running `schedule:run` every minute). Without it, results are only fetched when a request page is opened or "Check open orders now" is pressed.
- Arabic: all screen text goes through `__()` with `lang/ar.json` (check coverage with a key-extraction pass after adding views). Text typed by the admin (service names, descriptions, field labels) is not translated; announcements have their own Arabic text box.
- Unlinked services are still manual: an admin marks requests processing / completed / failed / cancelled.
- "EMI" was implemented as IMEI (15 digits); the user has not confirmed this reading.
- State and City are text boxes, not dropdowns (no dataset).
- Email (added 2026-11-14): Admin > Server Tools > Email settings writes the `MAIL_*` keys (admin address, SMTP account) straight into `.env` (`app/Support/EnvFile.php`) and re-caches config if it was cached; the password is never shown back. `app/Services/EmailNotifier.php` sends: admin ← new self-registered customer, new order; customer ← order completed (with the result code) / failed / cancelled, whether an admin or a provider closed it. Emails go out after the response (`defer`) and a mail error only reaches laravel.log. Email text is English only. Until `MAIL_MAILER=smtp` is saved, all mail (including password reset) is written to the log.
- Running under WAMP Apache at `/GitRepo/dhru-server/public` is untested.
- "DHRU Server" is a temporary project name.
