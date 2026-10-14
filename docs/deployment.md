# Deployment (Phase 3)

Target: one VPS for the Laravel API + admin, Cloudflare R2 (or S3) for images, EAS for app builds.

## 1. Server (Ubuntu 24.04, 1–2 GB RAM is enough for launch)

Either use **Laravel Forge** (provisions everything below and gives you a deploy button) or do it by hand:

```bash
# PHP 8.3 + extensions, MySQL 8, Nginx, Composer
sudo apt update && sudo apt install -y nginx mysql-server unzip git \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-intl php8.3-gd php8.3-bcmath
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
```

Database:

```sql
CREATE DATABASE handi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'handi'@'localhost' IDENTIFIED BY '<strong password>';
GRANT ALL ON handi.* TO 'handi'@'localhost';
```

Code:

```bash
sudo mkdir -p /var/www && cd /var/www
sudo git clone <your repo url> handi && sudo chown -R www-data:www-data handi
cd handi/backend
cp .env.production.example .env        # fill in every blank; never commit .env
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=CatalogSeeder --force      # the 30 starter dishes
php artisan handi:create-admin you@example.com         # prompts for a password
php artisan suggestions:generate
php artisan storage:link
```

## 2. Nginx + HTTPS

```bash
sudo cp backend/deploy/nginx.conf /etc/nginx/sites-available/handi   # edit server_name + PHP socket
sudo ln -s /etc/nginx/sites-available/handi /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo apt install -y certbot python3-certbot-nginx && sudo certbot --nginx -d api.handi.pk
```

## 3. Scheduler

One cron line runs both daily jobs (suggestions at 00:05, push at `DAILY_PUSH_TIME`, Pakistan time). See `backend/deploy/crontab.txt`.

## 4. Images on Cloudflare R2

1. Create bucket `handi-media`, enable a public custom domain (e.g. `media.handi.pk`).
2. Create an API token with Object Read & Write on that bucket.
3. Set `MEDIA_DISK=s3` and the `AWS_*` values in `.env` (template in `.env.production.example`).

Admin uploads then go straight to R2 and the API returns `https://media.handi.pk/...` URLs.

## 5. Every later deploy

```bash
cd /var/www/handi && bash backend/deploy/deploy.sh
```

## 6. Admin panel safety checklist

- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] Default seeded admin (`admin@handi.test` / `password`) **not** present — use `handi:create-admin`
- [ ] Only users with `is_admin = 1` can open `/admin` (`User::canAccessPanel`)
- [ ] HTTPS only, `SESSION_SECURE_COOKIE=true`
- [ ] Daily MySQL backup (Forge → Backups, or `mysqldump` cron to off-site storage)

## 7. Mobile builds (EAS)

```bash
cd frontend
npx eas-cli@latest login
npx eas-cli@latest init                                         # links the project, adds projectId (needed for push)
npx eas-cli@latest build --profile preview --platform android   # APK for testers
npx eas-cli@latest build --profile production --platform android # AAB for Play Store
npx eas-cli@latest submit --profile production --platform android
```

API URLs are EAS environment variables, not committed files: run `eas env:create` for `preview` and `production` (see frontend/README.md). `eas.json` only names the environment each profile uses.

Push notifications on Android also need FCM credentials: in the EAS dashboard → Credentials → Android → upload the Google service account key (one-time).

## 8. CI

`.github/workflows/ci.yml` runs the backend Pest suite and the app's typecheck + lint on every push and pull request.
