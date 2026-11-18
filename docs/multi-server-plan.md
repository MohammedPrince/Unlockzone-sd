# Plan: "Create Server" — client servers run from this one

Status: **proposal, nothing built yet.** Read it, answer the questions in section 9, then building starts.

## 1. What you asked for

In the admin panel, a new page **Servers > Create Server**. You fill in a client's name, domain and logo,
pick what that server is allowed to have, and press Create. The client then:

- opens **their own domain** (for example `https://fastunlock.com`) and sees **their own logo, name and colours text**;
- signs in to **their own admin panel** and adds their own categories, services, API providers, customers and credit;
- has **their own customers**, who register and order on that domain and never see Unlock-SD.

You (the owner) see all client servers in one list, can change their limits, suspend them, or set an expiry date.

## 2. How it would work (recommended design)

**One copy of the code, one database, a separate set of tables per server.**

The project already names every table with a prefix (`tbl_users`, `tbl_services`, ... set by `DB_PREFIX`).
Each client server gets its own prefix, for example `s1_users`, `s1_services`. When a request arrives, the app looks
at the domain, finds which server it belongs to, and switches to that server's tables before anything else runs.

```
unlock-sd.com      ->  tables tbl_*   (your main server, the owner)
fastunlock.com     ->  tables s1_*    (client 1)
gsm-king.net       ->  tables s2_*    (client 2)
```

Why this way:

| | One code + table prefix per server (recommended) | One database per server | Full copy of the site per client |
|---|---|---|---|
| Works on your cPanel hosting | Yes: no new database needed | Needs cPanel API token to create databases | Yes, but by hand |
| Update all servers | One `git pull` + one migrate command for all | Same | Pull and migrate each copy |
| Data kept apart | Yes: each server has its own tables | Yes | Yes |
| Changes to existing code | Small: almost every screen works as it is | Small | None |
| Risk of one client seeing another's data | Low: tables are physically separate | Lowest | None |

The other common approach, one set of tables with a `server_id` column on every row, is **not** recommended: every
query in the app would have to change, and one forgotten filter shows one client's orders to another.

## 3. What each client server gets (its own copy)

- Users (its own admin + its own customers), wallets, credit ledger.
- Categories, fields, services, orders.
- API providers (where it buys from) and API clients (who buys from it).
- Settings: site name, logos, home page texts, WhatsApp/Telegram, maintenance mode, ticker, terms.
- Announcements, notifications, activity log, visitor statistics.
- Uploaded files in its own folder: `public/uploads/servers/{id}/...`.
- Email settings (SMTP account) stored in its own settings, not in `.env`.

Shared by everyone: the code, the design (navy/orange theme), the Arabic/English texts.

## 4. Owner-only (never inside a client server)

- **Servers** page (create / edit / suspend / delete client servers).
- **Server Tools** (composer, migrations, cache, logs, `.env`): these change the whole installation, so only you
  have them. Client admins do not see this menu.
- The `.env` file, the hosting, the cron job.

## 5. Create Server form (what you choose)

**Basic**
- Server name, client contact (name, email, phone).
- Domain(s): main domain + optional `www`. Optional: a free subdomain like `client1.unlock-sd.com`.
- Logos: square logo, wide logo (light), wide logo (dark). Can also be uploaded later by the client.
- First admin account for the client: username, email, password (or "send invite by email").
- Status: Active / Suspended. Optional **expiry date** (after it, the client server shows a "service expired" page).

**Features (tick what the server is allowed to use)**
- API providers (buy from other DHRU servers)
- API clients (sell to other servers through `/api/index.php`)
- Announcement bar / ticker
- Home page editor (popup, offer, texts)
- Reports and visitor statistics
- Email notifications (own SMTP)
- Customer registration (open sign-up)
- Maintenance mode for their own site

Unticked = the menu item is hidden and the pages answer "not available on your plan".

**Limits (empty = no limit)**
- Max customers, max categories, max services, max API providers, max API clients, max admin accounts.
- Storage for uploads (MB).

When a limit is reached, the client sees "Limit reached (50 of 50 services). Contact {your name} to upgrade."

**Starting data (what to copy from your server)**
- Nothing (empty server).
- Selected categories with their services and fields (a tick list of your categories).
- Your settings texts (home page, terms) as a starting point.
- Optional: **connect the new server to Unlock-SD as its provider** (see question 9.2).

Prices and orders are never copied. Customers are never copied.

## 6. The domain step (outside the app)

The app cannot attach a domain to your hosting by itself. For each client domain, once:

1. Client points the domain's DNS **A record** to your server IP (`91.204.209.34`).
2. cPanel > **Domains** > Create a new domain > the client's domain, **Document Root = the same folder** as unlock-sd.com
   (or "Alias" / "Parked domain" on older cPanel), so it runs the same code.
3. cPanel > **SSL/TLS Status** > Run AutoSSL so it gets https.
4. The `public_html/.htaccess` rules must allow the new host (they currently only match `unlock-sd.com`;
   this will be changed to "any domain known to the app").

With subdomains of unlock-sd.com (`client1.unlock-sd.com`), steps 1–3 can be done once with a wildcard
`*.unlock-sd.com`, and new servers then work immediately.

Later (optional): with a cPanel API token in Server Tools, the app could add the domain and run AutoSSL itself.

## 7. Building it, in steps

Each step ends with tests passing and the main site working exactly as today.

1. **Foundation.** Table `servers` (owner tables): name, domains, prefix, status, expiry, features, limits.
   On every request: domain → server → switch table prefix and uploads folder. Unknown domain → plain 404.
   Command `php artisan servers:migrate` runs the migrations for every server (also from Server Tools).
2. **Create Server page.** The form in section 5, creating the tables, the first admin and copying the chosen data.
   List page with status, domain, counts (customers / services / orders), Suspend, Edit, "Open admin panel".
3. **Features and limits.** Hide menus, block pages, check limits on create (customers, services, providers ...).
4. **Per-server email, uploads and cron.** SMTP settings move into each server's settings; one cron job loops through
   all servers for provider order results.
5. **Suspend / expire / delete.** Suspended or expired server shows a page with your contact details.
   Delete asks twice and keeps a database backup first.
6. **Admin manual** (English + Arabic) and `docs/` notes updated.

Rough size: steps 1–3 are the main work; 4–6 are smaller. Your main server keeps working the whole time.

## 8. Risks and how they are handled

- **One client seeing another's data**: separate tables; plus a test for every main page that signs in to server A and
  checks nothing from server B appears.
- **A client admin reaching owner tools**: Servers and Server Tools exist only on the owner server; tests check
  that client admins get 404 there.
- **Updates**: one pull, then `servers:migrate` updates all servers. A failed migration on one server stops and is
  reported, the others are not touched.
- **Hosting load**: every server runs on the same hosting account. Many busy clients may need a bigger plan.
- **Backups**: the database backup in Server Tools will include all servers' tables; a per-server export is added.

## 9. Questions for you before building

1. **Domains**: will clients use their **own domains** (`fastunlock.com`), **subdomains** of yours
   (`client1.unlock-sd.com`), or both?
2. **Where client servers get services from**: should a new server be able to be **connected to Unlock-SD
   automatically** (your server becomes its API provider, so it resells your services and its orders and credit come
   through you), or do clients only use their own providers?
3. **Payment for the server**: just an expiry date that you extend by hand, or should the app track a monthly price
   and remind you?
4. **"Powered by Unlock-SD"** line in the client's footer: always, never, or a tick box per server?
5. **Signing in to a client's panel**: should you be able to open any client's admin panel without their password
   (an "Open as owner" button that is logged in the activity log)?
6. **Theme**: same navy/orange for everyone, or may each client pick their own main colour?
7. **Name**: what should the menu say: "Servers", "Client servers", "Sub-servers" or "Resellers"?
