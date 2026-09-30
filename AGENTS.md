<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.

# Deploy

Environment: Proxmox LXC, Debian 13, PHP 8.4, SQLite, served with `php artisan serve`, behind an nginx reverse proxy on the host.

## One-time setup (on the container)

```
apt install php8.4-cli php8.4-sqlite3 php8.4-mbstring php8.4-xml php8.4-curl php8.4-fpm unzip git composer
git clone <repo> /srv/ingleball
cd /srv/ingleball
cp .env.example .env && php8.4 artisan key:generate
# .env: APP_ENV=production, APP_DEBUG=false, APP_URL=https://..., MAIL_MAILER=smtp...
touch database/database.sqlite
chown -R www-data:www-data storage bootstrap/cache database
php8.4 artisan migrate --force
cp deploy/ingleball.service /etc/systemd/system/
systemctl daemon-reload && systemctl enable --now ingleball.service
```

The systemd unit runs `php8.4 artisan serve --host=0.0.0.0 --port=8000` as `www-data`; the front nginx proxies to `<LXC_IP>:8000` (see `deploy/nginx-front.conf`).

## Each release

```
sudo ./deploy.sh
```

`deploy.sh` does: `git pull --ff-only` (as `www-data`, needs an SSH deploy key to the repo), `composer install --no-dev --optimize-autoloader`, `migrate --force`, `config:cache`/`route:cache`/`view:cache`, then restarts the unit. No npm/vite build: CSS is a static file in `public/css`.
</laravel-boost-guidelines>
</laravel-boost-guidelines>
