# BluePrint HR on Ubuntu Server with Nginx

This guide deploys the Laravel port to an Ubuntu Server using PHP 8.3-FPM, MySQL 8, Nginx, Supervisor, and the built Vue/Vite assets. It assumes the application will run at `https://hr.example.com` and that the Laravel project is available in a Git repository or has been copied to `/var/www/blueprint-hr`.

> **Important:** Replace every placeholder such as `hr.example.com`, `CHANGE_DATABASE_PASSWORD`, and `/var/www/blueprint-hr` with your real values. Do not copy development `.env` files or the temporary SQLite database to production.

## 1. DNS and server prerequisites

Create an `A` record for `hr.example.com` pointing to the server’s public IPv4 address. Add an `AAAA` record only when IPv6 is correctly configured. Before continuing, confirm that DNS resolves to the intended server:

```bash
dig +short hr.example.com
```

Connect as a sudo-capable deployment user. The commands below assume Ubuntu Server 24.04 LTS and a clean server. The PHP extensions include `gd`, which is required by PhpSpreadsheet through Laravel Excel, and `intl`, `bcmath`, `zip`, and `mbstring`, which are common requirements for a Laravel production application.

```bash
sudo apt update
sudo apt upgrade -y
sudo apt install -y \
  nginx mysql-server supervisor certbot python3-certbot-nginx git curl unzip \
  php8.3-cli php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-bcmath php8.3-gd php8.3-intl \
  php8.3-opcache

sudo systemctl enable --now nginx mysql supervisor php8.3-fpm
php -v
php -m | grep -E 'bcmath|curl|gd|intl|mbstring|mysqli|openssl|PDO|pdo_mysql|xml|zip'
```

If the server has a firewall, allow only the required public services. SSH should be restricted to trusted source addresses where possible.

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
sudo ufw status verbose
```

## 2. Create the MySQL database

Use a dedicated database and least-privilege application user. Do not use the MySQL `root` account from Laravel. The password below is a placeholder and should be generated through a password manager or secret-management system.

```bash
sudo mysql <<'SQL'
CREATE DATABASE blueprint_hr CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'blueprint_hr'@'127.0.0.1' IDENTIFIED BY 'CHANGE_DATABASE_PASSWORD';
GRANT ALL PRIVILEGES ON blueprint_hr.* TO 'blueprint_hr'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
```

Validate the credentials before deploying the application:

```bash
mysql -h 127.0.0.1 -u blueprint_hr -p blueprint_hr -e 'SELECT VERSION();'
```

The application uses database-backed sessions, cache, and queues in the preferred production configuration. Laravel’s default cache and queue tables are included in the project migrations.

## 3. Install the application under `/var/www`

Create a release directory owned by the deployment user and grouped with `www-data`. Replace `DEPLOY_USER` with the account that will fetch and update releases. The source repository should contain the Laravel port, not the original React/Express source.

```bash
export DEPLOY_USER="$USER"
sudo mkdir -p /var/www/blueprint-hr
sudo chown -R "$DEPLOY_USER":www-data /var/www/blueprint-hr
cd /var/www

git clone YOUR_LARAVEL_PORT_REPOSITORY_URL blueprint-hr
cd /var/www/blueprint-hr
```

If the port is delivered as an archive rather than a Git repository, copy and extract it into `/var/www/blueprint-hr`, then verify that `artisan`, `composer.json`, `public/`, `storage/`, and `bootstrap/` are directly inside that directory.

Install PHP dependencies. Production does not need development packages.

```bash
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
```

Build the Vue/Vite frontend. The server needs Node only if assets are built on the server. A stronger release pipeline builds the assets in CI and deploys the generated `public/build` directory, allowing Node to be omitted from the production host.

```bash
# Use this only when building on the server.
sudo apt install -y nodejs npm
npm ci
npm run build
```

## 4. Configure the production environment

Create the environment file with restrictive permissions. Never commit it to Git or place it in a publicly served directory.

```bash
cd /var/www/blueprint-hr
cp .env.example .env
chmod 640 .env
chown "$DEPLOY_USER":www-data .env
```

Edit `/var/www/blueprint-hr/.env` and use values equivalent to the following. Keep `SESSION_DOMAIN` equal to the application host when the SPA and API share one hostname. Use a leading dot only when the same session must intentionally span multiple subdomains.

```dotenv
APP_NAME="BluePrint HR"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://hr.example.com

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=blueprint_hr
DB_USERNAME=blueprint_hr
DB_PASSWORD=CHANGE_DATABASE_PASSWORD

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=hr.example.com
SESSION_SECURE_COOKIE=true

CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

SANCTUM_STATEFUL_DOMAINS=hr.example.com

# Required only when deliberately running the development/demo seeder.
BLUEPRINT_ADMIN_EMAIL=admin@hr.example.com
BLUEPRINT_DEMO_PASSWORD=USE_A_STRONG_UNIQUE_SECRET

MAIL_MAILER=smtp
MAIL_HOST=YOUR_SMTP_HOST
MAIL_PORT=587
MAIL_USERNAME=YOUR_SMTP_USERNAME
MAIL_PASSWORD=YOUR_SMTP_PASSWORD
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@hr.example.com
MAIL_FROM_NAME="BluePrint HR"
```

Generate an application key only when this is a new installation. Do not regenerate it during an ordinary deployment because encrypted cookies and other encrypted application data would become invalid.

```bash
php artisan key:generate --force
```

Run migrations in production without automatically inserting demo data:

```bash
php artisan migrate --force
```

The included `DatabaseSeeder` is intentionally a development/demo seeder and requires `BLUEPRINT_DEMO_PASSWORD`. Do not run it against a real tenant unless the demo organization and statutory data are explicitly wanted. For a staging environment, the controlled command is:

```bash
BLUEPRINT_DEMO_PASSWORD='STAGING_ONLY_UNIQUE_SECRET' php artisan db:seed --force
```

For production, create the first tenant and administrator through an approved provisioning process, then remove or rotate any temporary credentials. Do not leave a shared demo password active.

## 5. Set Laravel permissions

Nginx and PHP-FPM must be able to write only to Laravel’s writable directories. Do not use `chmod -R 777`.

```bash
sudo chown -R "$DEPLOY_USER":www-data /var/www/blueprint-hr
sudo find /var/www/blueprint-hr/storage /var/www/blueprint-hr/bootstrap/cache -type d -exec chmod 775 {} \;
sudo find /var/www/blueprint-hr/storage /var/www/blueprint-hr/bootstrap/cache -type f -exec chmod 664 {} \;
```

If uploaded documents are stored on the local disk and need public access, create Laravel’s storage link. Review the privacy implications before exposing HR documents publicly.

```bash
php artisan storage:link
```

## 6. Cache configuration and views

After the production `.env` is complete, build Laravel’s optimized caches. Repeat the cache commands after any environment or route configuration change.

```bash
cd /var/www/blueprint-hr
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan about
```

If the application reports a configuration value from an old environment, clear and rebuild the configuration cache. Laravel reads environment values through the cached configuration in production.

## 7. Nginx configuration

Create `/etc/nginx/sites-available/blueprint-hr` with the following configuration. The critical details are that the document root is `/var/www/blueprint-hr/public`, all non-file requests are sent to `index.php`, and PHP-FPM receives the real script path.

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name hr.example.com;

    root /var/www/blueprint-hr/public;
    index index.php;

    client_max_body_size 25M;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    location ~* \.(?:css|js|jpg|jpeg|gif|png|svg|ico|webp|woff|woff2|ttf)$ {
        expires 7d;
        add_header Cache-Control "public, max-age=604800, immutable";
        try_files $uri =404;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable the site, remove the default site if it is not needed, validate the configuration, and reload Nginx.

```bash
sudo ln -s /etc/nginx/sites-available/blueprint-hr /etc/nginx/sites-enabled/blueprint-hr
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

The Nginx `root` must point to `public`, not `/var/www/blueprint-hr`. This prevents direct web access to `.env`, application source, Composer metadata, and other files outside the public web boundary.

## 8. Enable HTTPS with Certbot

Once DNS points to the server and the HTTP Nginx site responds, request a certificate. Certbot can update the Nginx configuration and add the HTTP-to-HTTPS redirect.

```bash
sudo certbot --nginx -d hr.example.com
sudo certbot renew --dry-run
```

After TLS is active, ensure `.env` contains `APP_URL=https://hr.example.com`, `SESSION_SECURE_COOKIE=true`, and `SANCTUM_STATEFUL_DOMAINS=hr.example.com`, then rebuild the configuration cache:

```bash
cd /var/www/blueprint-hr
php artisan config:clear
php artisan config:cache
sudo systemctl reload nginx
```

For a stricter final Nginx configuration, use an HTTPS server block with the certificate paths generated by Certbot and a separate port-80 block that returns a permanent redirect to HTTPS. Avoid adding a Content Security Policy until the actual Vite asset and inline markup behavior has been tested, because an incorrect policy can break the Vue application.

## 9. Configure Supervisor for queues

The port’s supplied worker template uses Laravel’s database queue:

```ini
[program:blueprint-hr-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/blueprint-hr/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/blueprint-hr-worker.log
stopwaitsecs=3600
```

Install and activate it:

```bash
sudo cp deploy/supervisor/blueprint-hr-worker.conf /etc/supervisor/conf.d/blueprint-hr-worker.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status blueprint-hr-worker:*
```

If a deployment changes worker code or environment values, restart the workers gracefully:

```bash
sudo supervisorctl restart blueprint-hr-worker:*
```

## 10. Schedule Laravel’s scheduler

Laravel’s scheduler needs one cron entry. Install it for the service account that owns the application runtime, commonly `www-data`:

```bash
sudo crontab -u www-data -e
```

Add exactly one entry:

```cron
* * * * * cd /var/www/blueprint-hr && php artisan schedule:run >> /dev/null 2>&1
```

Do not create multiple scheduler entries on the same host. If scheduled jobs are later added for payroll, notifications, or compliance reminders, they will be triggered by this single entry.

## 11. Verification checklist

Run the checks below after deployment. The health endpoint should return HTTP 200 without exposing application details.

```bash
curl -I https://hr.example.com
curl -fsS https://hr.example.com/up

cd /var/www/blueprint-hr
php artisan route:list --except-vendor
php artisan about
sudo nginx -t
sudo systemctl --no-pager --full status nginx php8.3-fpm mysql supervisor
sudo supervisorctl status blueprint-hr-worker:*
```

Then test the browser login. The Vue client should request `/sanctum/csrf-cookie`, submit `/api/auth/login`, and load `/api/auth/me` and `/api/dashboard`. If login returns `419`, check that the request is HTTPS, the `SESSION_DOMAIN` and `SANCTUM_STATEFUL_DOMAINS` values exactly match the host, the browser is accepting cookies, and the Axios XSRF settings are present in the built asset. If the application returns `502`, check the PHP-FPM socket path and `sudo journalctl -u php8.3-fpm`.

## 12. Deployment updates and rollback

A normal code deployment should use maintenance mode only when the migration or release requires it:

```bash
cd /var/www/blueprint-hr
php artisan down --render="errors::503"
git fetch --tags origin
git checkout YOUR_RELEASE_TAG
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo chown -R "$DEPLOY_USER":www-data /var/www/blueprint-hr
sudo supervisorctl restart blueprint-hr-worker:*
sudo systemctl reload php8.3-fpm
sudo nginx -t && sudo systemctl reload nginx
php artisan up
```

Take a database backup before migrations that change payroll, employee, or compliance data. For rollback, restore the previous code release and the corresponding database backup according to the tested runbook. Do not run `migrate:rollback` blindly against production because business data may already depend on the changed schema.

## 13. Operational security

Use SSH keys, disable password authentication where appropriate, keep Ubuntu security updates enabled, restrict MySQL to localhost unless a private network requires otherwise, and monitor Nginx, PHP-FPM, Supervisor, and Laravel logs. Store database, SMTP, and application secrets outside Git. Configure encrypted database backups and test restoration. Review HR document retention and access requirements before using local storage for uploaded files.

## References

[1]: https://laravel.com/docs/12.x Laravel 12 documentation
[2]: https://laravel.com/docs/12.x/sanctum Laravel Sanctum documentation
[3]: https://laravel.com/docs/12.x/deployment Laravel deployment documentation
[4]: https://nginx.org/en/docs/ Nginx documentation
[5]: https://supervisord.org/ Supervisor documentation
[6]: https://certbot.eff.org/instructions Certbot instructions
