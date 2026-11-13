# Deploying Unlock-SD (cPanel / LiteSpeed hosting)

The web server must serve the project's **`public/`** folder, never the project folder itself.
If it points anywhere else you get the host's own "404 Not Found" page for every address.

## 1. Folders

Put the project **outside** `public_html`, for example:

```
/home/USER/unlock-sd/          <- the whole project (app, config, vendor, .env ...)
/home/USER/unlock-sd/public/   <- the subdomain's Document Root
```

cPanel > **Domains** (or **Subdomains**) > `unlock.sunxfinder.com` > **Document Root** =
`/home/USER/unlock-sd/public`

## 2. Get the code

- cPanel > **Git Version Control** > Create > clone `https://github.com/MohammedPrince/Unlockzone-sd.git`
  into `/home/USER/unlock-sd`, branch `main`. Later updates: **Pull**.
- Or upload a ZIP of the repository and extract it there.

`vendor/` is not in Git. In cPanel **Terminal** (or SSH):

```
cd ~/unlock-sd
composer install --no-dev --optimize-autoloader
```

No terminal? Run `composer install --no-dev` on your PC and upload the `vendor/` folder.

## 3. PHP

cPanel > **MultiPHP Manager** (or **Select PHP Version**): PHP **8.2 or newer** for the subdomain,
with the extensions `pdo_mysql`, `mbstring`, `intl`, `dom`, `gd`, `fileinfo`, `openssl`, `curl`.

## 4. Database

cPanel > **MySQL Databases**: create a database and a user, add the user to the database with ALL PRIVILEGES.

## 5. .env

```
cd ~/unlock-sd
cp .env.example .env        # then edit DB_DATABASE, DB_USERNAME, DB_PASSWORD
php artisan key:generate
php artisan migrate --seed --force
php artisan storage:link
php artisan optimize
```

`--seed` creates the admin account (`admin` / `Admin@12345`). **Sign in at `/admin/login` and change the password at once.**

Folders the app writes to must be writable (755 / 775): `storage/` and `bootstrap/cache/`,
and `public/uploads/` for logos and description images.

## 6. Cron (provider orders)

cPanel > **Cron Jobs**, every minute:

```
* * * * * cd /home/USER/unlock-sd && php artisan schedule:run >> /dev/null 2>&1
```

It fetches results for orders sent to connected API providers. (Use the full PHP path the host gives,
for example `/usr/local/bin/php`, if plain `php` is not found.)

## 7. Updating later

1. Admin > **Server Tools** > **Database backup**.
2. Pull the new code (Git Version Control > Pull, or upload).
3. Server Tools: **Composer install** (only if composer.json / composer.lock changed),
   **Clear caches**, **Run migrations**, **Optimize**.

## If the host will not serve `public/` as the Document Root

Some hosts (this one included: `unlock.sunxfinder.com`) only work with the **project folder** as the
Document Root, e.g. `public_html/Repository/unlockSD`. That is supported: the project ships a
`.htaccess` next to `artisan` that passes every request into `public/`, so `.env`, `storage/`,
`vendor/` and the rest are never served. Nothing else to configure.
