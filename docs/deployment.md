# Deployment

**Production:** https://handi.hegemonicinc.com — Laravel API + Filament admin on one Ubuntu VPS with Nginx, PHP-FPM and MySQL. The mobile app is built with EAS and talks to `https://handi.hegemonicinc.com/api/v1`.

| What | URL |
|---|---|
| API | https://handi.hegemonicinc.com/api/v1 |
| Health check | https://handi.hegemonicinc.com/api/v1/health |
| Admin panel | https://handi.hegemonicinc.com/admin |
| API docs (super admins) | https://handi.hegemonicinc.com/docs/api |

Paths used below: code in `/var/www/handi`, Laravel in `/var/www/handi/backend`, web root `/var/www/handi/backend/public`.

---

## 1. DNS

At your DNS provider, add an **A record** `handi` → your server's public IPv4 (and an AAAA record for IPv6 if the server has one). Check it before going further:

```bash
dig +short handi.hegemonicinc.com     # must print the server IP
```

If the domain is on Cloudflare with the orange cloud (proxy) on, set SSL mode to **Full (strict)** and `TRUSTED_PROXIES=*` in `.env` (step 5).

## 2. Server packages (Ubuntu 22.04 / 24.04)

PHP **8.2 or newer** works (the production server runs 8.2). Use the same version in every command below and in the Nginx `fastcgi_pass` line.

```bash
sudo apt update
sudo apt install -y nginx mysql-server git unzip curl supervisor \
  php8.2-fpm php8.2-cli php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl \
  php8.2-zip php8.2-intl php8.2-gd php8.2-bcmath
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer

php -v && composer -V && ls /run/php/          # note the php8.x-fpm.sock name
```

Allow 10 MB photo uploads in PHP — edit `/etc/php/8.2/fpm/php.ini`:

```ini
upload_max_filesize = 12M
post_max_size = 13M
memory_limit = 256M
```

```bash
sudo systemctl restart php8.2-fpm
```

## 3. Database

```bash
sudo mysql
```

```sql
CREATE DATABASE handi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'handi'@'localhost' IDENTIFIED BY '<strong password>';
CREATE USER 'handi'@'127.0.0.1' IDENTIFIED BY '<strong password>';
GRANT ALL PRIVILEGES ON handi.* TO 'handi'@'localhost';
GRANT ALL PRIVILEGES ON handi.* TO 'handi'@'127.0.0.1';
FLUSH PRIVILEGES;
EXIT;
```

Test it: `mysql -u handi -p -h 127.0.0.1 handi`. Laravel uses this `handi` user — not `root` (on Ubuntu, root only logs in with `sudo mysql`).

**Start over** (deletes all data): in `sudo mysql` run `DROP DATABASE IF EXISTS handi; DROP USER IF EXISTS 'handi'@'localhost'; DROP USER IF EXISTS 'handi'@'127.0.0.1';` and repeat the block above.

## 4. Code

```bash
sudo mkdir -p /var/www && cd /var/www
sudo git clone https://github.com/CaptainArslan/handi.git handi
sudo chown -R $USER:www-data handi
cd handi/backend
composer install --no-dev --optimize-autoloader
```

## 5. Environment (`backend/.env`)

```bash
cp .env.production.example .env
php artisan key:generate
nano .env
```

Fill in at least:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://handi.hegemonicinc.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=handi
DB_USERNAME=handi
DB_PASSWORD="<strong password>"        # keep the quotes

SESSION_SECURE_COOKIE=true
MEDIA_DISK=public                      # or s3 for Cloudflare R2 (section 9)

ADMIN_EMAIL=you@hegemonicinc.com       # first super admin, created by the seeder
ADMIN_PASSWORD="<strong password>"

MAIL_MAILER=smtp                       # needed for password-reset codes
MAIL_HOST=...
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=no-reply@hegemonicinc.com
```

`.env` is never committed. After **any** `.env` change run `php artisan config:clear` (or `php artisan optimize`) — otherwise Laravel keeps using the old cached values.

## 6. Database tables, admin and permissions

```bash
php artisan config:clear
php artisan migrate --force
php artisan db:seed --force            # categories, dish library, cities + super admin from ADMIN_*
php artisan storage:link

sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache

php artisan optimize
php artisan filament:optimize
```

More admins later: `php artisan handi:create-admin someone@hegemonicinc.com`.

Don't set `DEMO_USER_*` in production — the seeder would create a demo account.

## 7. Nginx + HTTPS

The ready-made config is [`backend/deploy/nginx.conf`](../backend/deploy/nginx.conf) (`server_name handi.hegemonicinc.com`, root `/var/www/handi/backend/public`, 12 MB uploads). Check its `fastcgi_pass` socket matches `ls /run/php/`.

```bash
sudo cp /var/www/handi/backend/deploy/nginx.conf /etc/nginx/sites-available/handi
sudo ln -sf /etc/nginx/sites-available/handi /etc/nginx/sites-enabled/handi
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx

sudo ufw allow 'Nginx Full'             # if the firewall is on
curl -I http://handi.hegemonicinc.com/api/v1/health

sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d handi.hegemonicinc.com --redirect
```

Certbot adds the 443 block and the HTTP → HTTPS redirect, and renews automatically (`sudo certbot renew --dry-run` to check).

**Check:** https://handi.hegemonicinc.com/api/v1/health returns `"status": "ok"`, and https://handi.hegemonicinc.com/admin shows the sign-in page.

## 8. Scheduler and queue worker

**Scheduler** (morning reminders every minute, Telescope pruning) — add the line from [`backend/deploy/crontab.txt`](../backend/deploy/crontab.txt):

```bash
sudo crontab -e -u www-data
# * * * * * cd /var/www/handi/backend && php artisan schedule:run >> /dev/null 2>&1
```

**Queue worker** — only needed with `MEDIA_QUEUE_CONVERSIONS=true` (photo sizes made in the background):

```bash
sudo cp /var/www/handi/backend/deploy/handi-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl status
```

## 9. Photos on Cloudflare R2 (optional)

By default photos stay on the server (`MEDIA_DISK=public`, served from `/storage/...`). To move them to R2:

1. Create bucket `handi-media` and give it a public custom domain, e.g. `media.hegemonicinc.com`.
2. Create an R2 API token with Object Read & Write on that bucket.
3. In `.env`: `MEDIA_DISK=s3` and the `AWS_*` values (template in `.env.production.example`), then `php artisan config:clear`.
4. Existing photos: re-upload, or copy `storage/app/public` into the bucket.

## 10. Every later deploy

```bash
cd /var/www/handi && bash backend/deploy/deploy.sh
```

It pulls `main`, goes into maintenance mode, runs `composer install`, migrations and caches, restarts the queue worker and comes back up. If you ever change files by hand, finish with `php artisan optimize:clear && php artisan optimize`.

## 11. Mobile app builds (EAS)

The app's server address is an EAS environment variable, set once per environment:

```bash
cd frontend
npx eas-cli@latest login
npx eas-cli@latest init                    # first time: links the project (needed for push)

npx eas-cli@latest env:create --environment production --name EXPO_PUBLIC_API_BASE_URL \
  --value https://handi.hegemonicinc.com/api/v1 --visibility plaintext
npx eas-cli@latest env:create --environment preview --name EXPO_PUBLIC_API_BASE_URL \
  --value https://handi.hegemonicinc.com/api/v1 --visibility plaintext

npx eas-cli@latest build --profile preview --platform android      # APK for testers
npx eas-cli@latest build --profile production --platform android   # AAB for the Play Store
npx eas-cli@latest submit --profile production --platform android
```

To point a local development build at production, set `EXPO_PUBLIC_API_BASE_URL=https://handi.hegemonicinc.com/api/v1` in `frontend/.env` and run `npx expo start -c`.

Push notifications on Android also need FCM credentials: EAS dashboard → Credentials → Android → upload the Google service account key (one time).

## 12. Production checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://handi.hegemonicinc.com`
- [ ] HTTPS works and HTTP redirects to it; `SESSION_SECURE_COOKIE=true`
- [ ] `/api/v1/health` returns `ok`
- [ ] Only `is_admin` users can open `/admin`; no demo user (`DEMO_USER_*` empty)
- [ ] `TELESCOPE_ENABLED=false` unless you're debugging
- [ ] `storage/` and `bootstrap/cache/` owned by `www-data`
- [ ] Scheduler cron added (and the worker, if `MEDIA_QUEUE_CONVERSIONS=true`)
- [ ] Mail configured (password-reset codes)
- [ ] Daily MySQL backup, e.g. `mysqldump handi | gzip > /backups/handi-$(date +%F).sql.gz` from cron, copied off the server

## 13. Troubleshooting

| Symptom | Fix |
|---|---|
| `Access denied for user 'root'@'localhost'` | Laravel is using cached or default settings: check `.env` has `DB_USERNAME=handi`, then `php artisan config:clear` |
| `Access denied for user 'handi'@…` | Password mismatch, or the user is missing for that host: redo section 3 |
| 502 Bad Gateway | PHP-FPM socket in Nginx doesn't match `ls /run/php/`; `sudo systemctl status php8.2-fpm` |
| 500 error, blank page | `tail -n 50 storage/logs/laravel.log`; usually permissions on `storage/` (section 6) |
| 413 Request Entity Too Large | Raise `client_max_body_size` (Nginx) and `upload_max_filesize` / `post_max_size` (PHP) |
| Photos 404 | `php artisan storage:link`; `APP_URL` must be `https://handi.hegemonicinc.com` |
| Admin shows `http://` links or redirect loops | `APP_URL` must use `https`; behind Cloudflare set `TRUSTED_PROXIES=*` |
| Changes to `.env` ignored | `php artisan config:clear` (then `php artisan optimize`) |
| App says "Can't reach Handi" | The build's `EXPO_PUBLIC_API_BASE_URL` must be `https://handi.hegemonicinc.com/api/v1`; rebuild after changing it |
