# Local Setup Guide

Setup the project on your local machine using XAMPP, MAMP, Laragon, or any local PHP environment.

## Prerequisites

- PHP 8.3+
- Composer
- MySQL

---

## Step-by-Step Setup

### Step 1: Clone the Repository

```bash
git clone <repository-url>
cd subscription-engine
```

### Step 2: Install Dependencies

```bash
composer install
```

This installs all PHP packages defined in `composer.json`.

### Step 3: Configure Environment

```bash
cp .env.example .env
php artisan key:generate
```

This creates the `.env` file from the example template and generates a unique application key.

### Step 4: Configure Database

Open the `.env` file and set your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=subscription_engine
DB_USERNAME=root
DB_PASSWORD=your_password_here
```

| Variable | Description |
|----------|-------------|
| `DB_CONNECTION` | Database driver (mysql) |
| `DB_HOST` | Database host (usually 127.0.0.1) |
| `DB_PORT` | Database port (3306 for MySQL) |
| `DB_DATABASE` | Your database name |
| `DB_USERNAME` | Database username |
| `DB_PASSWORD` | Database password |

### Step 5: Create Database

Create the database using MySQL:

```bash
mysql -u root -p -e "CREATE DATABASE subscription_engine;"
```

Or create it manually via **phpMyAdmin**:
1. Open http://localhost/phpmyadmin
2. Click **"New"** in the left sidebar
3. Enter database name: `subscription_engine`
4. Click **"Create"**

### Step 6: Run Migrations & Seed Data

```bash
php artisan migrate --seed
```

This command:
- Creates all database tables (users, plans, plan_prices, subscriptions, subscription_audit_logs)
- Seeds 3 demo plans (Starter, Professional, Enterprise) with pricing in USD, AED, and EGP
- Creates a test user for authenticated endpoint testing

**After seeding, you will see test credentials in the console:**

| Field | Value |
|-------|-------|
| **Email** | `test@example.com` |
| **Password** | `TestPass123` |

### Step 7: Start the Server

```bash
php artisan serve
```

Open: http://localhost:8000

---

## Test the API

### 1. Login to Get Token

```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"TestPass123"}'
```

Response:
```json
{
  "status": "success",
  "message": "Login successful.",
  "data": {
    "user": { "id": 1, "name": "Test User", "email": "test@example.com" },
    "token": "1|abc123...",
    "token_type": "Bearer"
  }
}
```

### 2. Use Token to Access Protected Routes

```bash
curl http://localhost:8000/api/v1/subscriptions \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

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

## Environment-Specific Setup

### XAMPP (Windows/Linux)

1. Start **Apache** and **MySQL** from XAMPP Control Panel
2. Create database via phpMyAdmin: http://localhost/phpmyadmin
3. Update `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=subscription_engine
DB_USERNAME=root
DB_PASSWORD=
```

4. Run: `php artisan migrate --seed`
5. Start: `php artisan serve`

### MAMP (macOS)

1. Start MAMP servers
2. Create database via phpMyAdmin: http://localhost:8888/phpMyAdmin
3. Update `.env`:

```env
DB_HOST=127.0.0.1
DB_PORT=8889
DB_DATABASE=subscription_engine
DB_USERNAME=root
DB_PASSWORD=root
```

4. Run: `php artisan migrate --seed`
5. Start: `php artisan serve`

### Laragon (Windows)

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

4. Run: `composer setup` (Laragon supports this shortcut)
5. Start: `php artisan serve`

---

## Troubleshooting

### PHP Version Error

```bash
php -v
```

Must be **PHP 8.3+**. If not, upgrade or use [Laragon](https://laragon.org/) or [Herd](https://herd.laravel.com/).

### Composer Not Found

Install Composer: https://getcomposer.org/download/

### Database Connection Refused

- Make sure MySQL is running
- Check credentials in `.env`
- Test connection: `mysql -u root -p`

### Permission Denied (Linux/Mac)

```bash
chmod -R 775 storage bootstrap/cache
chown -R $USER:www-data storage bootstrap/cache
```

### Class Not Found

```bash
composer dump-autoload
```
