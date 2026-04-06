# Local Setup Guide

Setup the project on your local machine using XAMPP, MAMP, Laragon, or any local PHP environment.

## Prerequisites

- PHP 8.3+
- Composer
- MySQL

## Quick Start

### 1. Clone the Repository

```bash
git clone <repository-url>
cd subscription-engine
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Configure Environment

```bash
cp .env.example .env
php artisan key:generate
```

### 4. Configure Database

Edit the `.env` file and set your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=subscription_engine
DB_USERNAME=root
DB_PASSWORD=
```

### 5. Create Database

```bash
mysql -u root -p -e "CREATE DATABASE subscription_engine;"
```

Or create it via phpMyAdmin.

### 6. Run Migrations & Seed Data

```bash
php artisan migrate --seed
```

### 7. Start the Server

```bash
php artisan serve
```

Open: http://localhost:8000

---

## Test Credentials

After seeding, you can test authenticated endpoints with:

| Field | Value |
|-------|-------|
| **Email** | `test@example.com` |
| **Password** | `TestPass123` |

---

## Common Commands

### Run Tests

```bash
php artisan test
```

### Clear Cache

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### Run Scheduler (for subscription automation)

```bash
php artisan schedule:run
```

Or add to your system crontab:

```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

### Run Queue Worker

```bash
php artisan queue:work
```

---

## XAMPP Setup (Windows/Linux)

1. Start Apache and MySQL from XAMPP Control Panel
2. Create database via phpMyAdmin:
   - Open http://localhost/phpmyadmin
   - Click "New" → Database name: `subscription_engine` → Create
3. Update `.env` with your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=subscription_engine
DB_USERNAME=root
DB_PASSWORD=
```

4. Run migrations:

```bash
php artisan migrate --seed
```

5. Start server:

```bash
php artisan serve
```

---

## MAMP Setup (macOS)

1. Start MAMP servers
2. Create database via phpMyAdmin:
   - Open http://localhost:8888/phpMyAdmin
   - Click "New" → Database name: `subscription_engine` → Create
3. Update `.env`:

```env
DB_HOST=127.0.0.1
DB_PORT=8889
DB_DATABASE=subscription_engine
DB_USERNAME=root
DB_PASSWORD=root
```

4. Run migrations:

```bash
php artisan migrate --seed
```

5. Start server:

```bash
php artisan serve
```

---

## Laragon Setup (Windows)

1. Start Laragon
2. Open Terminal → Create database:

```bash
mysql -u root -e "CREATE DATABASE subscription_engine;"
```

3. Update `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=subscription_engine
DB_USERNAME=root
DB_PASSWORD=
```

4. Run setup:

```bash
composer setup
```

5. Start server:

```bash
php artisan serve
```

---

## Troubleshooting

### PHP Version Error

Check your PHP version:

```bash
php -v
```

Must be **PHP 8.3+**. If not, upgrade or use a tool like [Laragon](https://laragon.org/) or [Herd](https://herd.laravel.com/).

### Composer Not Found

Install Composer: https://getcomposer.org/download/

### Database Connection Refused

- Make sure MySQL/MariaDB is running
- Check credentials in `.env`
- Try connecting manually via MySQL client or phpMyAdmin

### Permission Denied (Linux/Mac)

```bash
chmod -R 775 storage bootstrap/cache
chown -R $USER:www-data storage bootstrap/cache
```

### Class Not Found

```bash
composer dump-autoload
```
