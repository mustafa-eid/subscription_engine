# Project Operating Instructions

## Subscription Management API — Trendline

This document provides comprehensive operating instructions for deploying, monitoring, maintaining, and troubleshooting the Subscription Management API in production environments.

---

## Table of Contents

1. [System Requirements](#1-system-requirements)
2. [Environment Configuration](#2-environment-configuration)
3. [Deployment](#3-deployment)
4. [Database Operations](#4-database-operations)
5. [Scheduler & Cron Setup](#5-scheduler--cron-setup)
6. [Queue Configuration](#6-queue-configuration)
7. [Monitoring & Logging](#7-monitoring--logging)
8. [Backup & Recovery](#8-backup--recovery)
9. [Scaling](#9-scaling)
10. [Security](#10-security)
11. [Troubleshooting](#11-troubleshooting)
12. [Maintenance Procedures](#12-maintenance-procedures)
13. [Runbook: Common Scenarios](#13-runbook-common-scenarios)

---

## 1. System Requirements

### Minimum Requirements

| Component | Version | Notes |
|-----------|---------|-------|
| PHP | 8.3+ | Required extensions: `mbstring`, `xml`, `curl`, `mysql`, `sqlite`, `redis` (if using Redis cache/queue) |
| Composer | 2.5+ | Dependency management |
| Database | MySQL 8.0+ / PostgreSQL 14+ / SQLite 3.35+ | MySQL recommended for production |
| Web Server | Nginx 1.24+ / Apache 2.4+ | Nginx recommended |
| OS | Ubuntu 22.04 LTS / AlmaLinux 9+ | Linux recommended |

### Recommended Production Stack

- **PHP-FPM** with OPcache enabled
- **Nginx** as reverse proxy
- **MySQL 8.0** with InnoDB engine
- **Redis** for cache and queue driver
- **Supervisor** for queue workers

---

## 2. Environment Configuration

### Required `.env` Variables

```bash
# Application
APP_NAME="Subscription Management API"
APP_ENV=production
APP_KEY=base64:YOUR_GENERATED_KEY
APP_DEBUG=false
APP_URL=https://api.yourdomain.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=subscription_engine
DB_USERNAME=app_user
DB_PASSWORD=secure_password

# Cache & Session
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Logging
LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=info

# Sanctum (API Authentication)
SANCTUM_STATEFUL_DOMAINS=api.yourdomain.com
```

### Key Generation

```bash
php artisan key:generate
```

### Configuration Caching (Production)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> **Warning:** Never run `config:cache` in development. It prevents `.env` changes from taking effect until the cache is cleared.

---

## 3. Deployment

### Initial Setup

```bash
# 1. Clone repository
git clone <repository-url> /var/www/subscription-engine
cd /var/www/subscription-engine

# 2. Install dependencies (production)
composer install --no-dev --optimize-autoloader

# 3. Configure environment
cp .env.example .env
# Edit .env with production values

# 4. Generate application key
php artisan key:generate

# 5. Run migrations with seed (optional: omit --seed in production)
php artisan migrate --force

# 6. Set file permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# 7. Cache configuration
php artisan config:cache
php artisan route:cache
```

### Deployment Checklist

- [ ] `.env` configured with production values
- [ ] `APP_DEBUG=false`
- [ ] `APP_ENV=production`
- [ ] Database migrations run successfully
- [ ] Sanctum migrations published and run
- [ ] File permissions set correctly
- [ ] Configuration cached
- [ ] Scheduler cron entry added
- [ ] Queue workers configured (if using async events)
- [ ] SSL certificate installed
- [ ] Rate limiting verified
- [ ] Health check endpoint accessible (`/up`)

### Zero-Downtime Deployment

```bash
# In your deployment script:
cd /var/www/subscription-engine

# Pull latest code
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader

# Run migrations
php artisan migrate --force

# Clear and rebuild caches
php artisan config:clear
php artisan route:clear
php artisan config:cache
php artisan route:cache

# Restart queue workers (if using Supervisor)
sudo supervisorctl restart subscription-queue:*

# Restart PHP-FPM
sudo systemctl reload php8.3-fpm
```

---

## 4. Database Operations

### Migrations

```bash
# Run pending migrations
php artisan migrate --force

# Rollback last batch
php artisan migrate:rollback

# Reset all migrations
php artisan migrate:reset

# Fresh migration (drops all tables)
php artisan migrate:fresh --force
```

### Seeding

```bash
# Run all seeders
php artisan db:seed --force

# Run specific seeder
php artisan db:seed --class=PlanSeeder --force
```

### Database Maintenance

```bash
# Show migration status
php artisan migrate:status

# Prune soft-deleted records older than 30 days
php artisan model:prune --model=App\Models\Plan --model=App\Models\Subscription
```

### Index Verification

The following indexes should exist for optimal query performance:

| Table | Index | Purpose |
|-------|-------|---------|
| `plans` | `is_active` | Filter active plans |
| `plan_prices` | `plan_currency_cycle_unique` | Prevent duplicate pricing |
| `plan_prices` | `currency` | Price lookups by currency |
| `plan_prices` | `billing_cycle` | Price lookups by cycle |
| `subscriptions` | `status` | Filter by subscription state |
| `subscriptions` | `user_id` | User subscription lookups |
| `subscriptions` | `trial_ends_at` | Expired trial detection |
| `subscriptions` | `grace_period_ends_at` | Grace period detection |
| `subscriptions` | `user_id + status` | Composite lookup |
| `subscriptions` | `active_user_id` (unique) | Idempotency constraint |

---

## 5. Scheduler & Cron Setup

### Server Cron Entry

Add this single line to your server's crontab (`crontab -e`):

```cron
* * * * * cd /var/www/subscription-engine && php artisan schedule:run >> /dev/null 2>&1
```

This triggers Laravel's scheduler every minute, which then decides which scheduled commands to run based on their defined schedules.

### Scheduled Command Details

| Command | Schedule | Purpose |
|---------|----------|---------|
| `subscriptions:process --chunk-size=100` | Daily at 00:00 UTC | Process expired trials and grace periods |

### Manual Execution

```bash
# Run the scheduler command manually
php artisan subscriptions:process --chunk-size=100

# Dry run (preview without applying changes)
php artisan subscriptions:process --dry-run

# Custom chunk size for large datasets
php artisan subscriptions:process --chunk-size=500
```

### Scheduler Output

Scheduler output is logged to:
```
storage/logs/scheduler-subscriptions.log
```

### Monitoring Scheduler Health

```bash
# Check if the scheduler ran recently
tail -50 storage/logs/scheduler-subscriptions.log

# Verify cron is running
systemctl status cron
```

---

## 6. Queue Configuration

### When to Use Queues

Domain events (`SubscriptionActivated`, `SubscriptionPastDue`, `SubscriptionCanceled`) are dispatched synchronously by default. For production systems with event listeners (emails, webhooks, analytics), configure async queue processing:

```bash
# .env
QUEUE_CONNECTION=redis
```

### Supervisor Configuration

Create `/etc/supervisor/conf.d/subscription-queue.conf`:

```ini
[program:subscription-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/subscription-engine/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasuser=false
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/subscription-engine/storage/logs/queue-worker.log
stopwaitsecs=3600
```

```bash
# Reload Supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start subscription-queue:*
```

### Queue Monitoring

```bash
# Check queue status
php artisan queue:monitor redis

# List failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Flush failed jobs
php artisan queue:flush
```

---

## 7. Monitoring & Logging

### Log Files

| Log File | Purpose |
|----------|---------|
| `storage/logs/laravel.log` | Application logs (all lifecycle transitions) |
| `storage/logs/scheduler-subscriptions.log` | Scheduler command output |
| `storage/logs/queue-worker.log` | Queue worker output (if using queues) |
| `/var/log/nginx/error.log` | Nginx error log |
| `/var/log/nginx/access.log` | Nginx access log |

### Structured Log Format

All subscription state transitions are logged with structured context:

```json
{
  "message": "Trial expired — subscription activated.",
  "context": {
    "subscription_id": 1,
    "user_id": 1,
    "old_status": "trialing",
    "new_status": "active",
    "processed_at": "2026-04-04T00:00:00+00:00"
  },
  "level": "info"
}
```

### Key Log Patterns to Monitor

```bash
# Failed state transitions
grep "Failed to process" storage/logs/laravel.log

# Payment failures
grep "Payment failed" storage/logs/laravel.log

# Grace period expirations
grep "Grace period expired" storage/logs/laravel.log

# Duplicate subscription attempts
grep "already has an active subscription" storage/logs/laravel.log

# Scheduler errors
grep -i "error\|exception\|failed" storage/logs/scheduler-subscriptions.log
```

### Health Check Endpoint

```bash
# Laravel's built-in health check
curl -s https://api.yourdomain.com/up
# Returns: {"status":"ok"} or {"status":"error"}
```

### Metrics to Track

| Metric | Alert Threshold | Action |
|--------|----------------|--------|
| Failed queue jobs | > 10 | Investigate and retry |
| 5xx error rate | > 1% | Check application logs |
| Response time (p95) | > 500ms | Check database queries |
| Scheduler last run | > 25 hours ago | Check cron daemon |
| Disk space (storage/) | > 80% | Rotate logs |
| Database connections | > 80% of max | Optimize queries or scale |

---

## 8. Backup & Recovery

### Database Backup

```bash
# MySQL backup
mysqldump -u app_user -p subscription_engine > backup_$(date +%Y%m%d_%H%M%S).sql

# Compressed backup
mysqldump -u app_user -p subscription_engine | gzip > backup_$(date +%Y%m%d_%H%M%S).sql.gz
```

### Automated Backup (Cron)

```cron
0 2 * * * mysqldump -u app_user -p'secure_password' subscription_engine | gzip > /backups/subscription_$(date +\%Y\%m\%d).sql.gz
```

### Restore from Backup

```bash
# Drop and recreate database
mysql -u app_user -p -e "DROP DATABASE subscription_engine; CREATE DATABASE subscription_engine;"

# Restore
gunzip < backup_20260404.sql.gz | mysql -u app_user -p subscription_engine

# Run migrations to ensure schema is current
php artisan migrate --force
```

### File Backups

Ensure these directories are included in your backup strategy:

- `storage/logs/` — Application logs
- `storage/app/` — Uploaded files (if any)
- `.env` — Environment configuration (store securely, not in git)

---

## 9. Scaling

### Horizontal Scaling

The application is stateless and can be scaled horizontally behind a load balancer:

1. **Multiple App Servers:** Deploy identical copies behind a load balancer (Nginx, HAProxy, AWS ALB).
2. **Shared Session/Cache:** Use Redis for cache and session drivers.
3. **Database:** Use a managed database service (AWS RDS, DigitalOcean Managed MySQL) with read replicas.
4. **Scheduler:** The `onOneServer()` directive ensures the scheduler runs on only one server.
5. **Queue:** Use Redis or Amazon SQS as the queue driver.

### Database Scaling

- **Read Replicas:** Configure read replicas for `GET` endpoints (plans listing, subscription listing).
- **Connection Pooling:** Use ProxySQL or PgBouncer for high-traffic deployments.

### Rate Limiting at Scale

For high-traffic deployments, consider moving rate limiting to the load balancer or API gateway level (e.g., Kong, AWS API Gateway) for better performance.

---

## 10. Security

### API Authentication

- All protected endpoints require a valid Sanctum Bearer token.
- Tokens should be issued through a separate authentication flow (not included in this API).
- Token expiration should be configured in `config/sanctum.php`.

### Rate Limiting

- Default: 60 requests per minute per authenticated user (or per IP for unauthenticated requests).
- Adjust in `bootstrap/app.php` if different limits are needed.

### Input Validation

- All user input is validated through Form Request classes before reaching controllers.
- Enum validation ensures only valid currency and billing cycle values are accepted.

### Database Security

- Use parameterized queries (Eloquent handles this automatically).
- Never expose database credentials in version control.
- Use database users with minimal required privileges.

### CORS Configuration

If the API is accessed from a browser-based frontend, configure CORS in `config/cors.php`:

```php
return [
    'paths' => ['api/v1/*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
    'allowed_origins' => ['https://your-frontend.com'],
    'allowed_headers' => ['Content-Type', 'Authorization'],
    'supports_credentials' => true,
];
```

---

## 11. Troubleshooting

### Common Issues

#### Scheduler Not Running

```bash
# Check if cron daemon is running
systemctl status cron

# Verify cron entry
crontab -l

# Test scheduler manually
php artisan schedule:run
```

#### "Rate limiter [api] is not defined" Error

This occurs if the rate limiter is not properly configured. Ensure `bootstrap/app.php` has the `throttleApi()` middleware configured.

#### Duplicate Subscription Created

Check the idempotency constraint:

```bash
# Verify the unique index exists
php artisan tinker --execute="echo \Illuminate\Support\Facades\Schema::hasIndex('subscriptions', 'subscriptions_active_user_id_unique') ? 'YES' : 'NO';"
```

#### Slow API Responses

```bash
# Check slow queries in MySQL
mysql -u root -p -e "SHOW FULL PROCESSLIST;"

# Check Laravel query log (enable in .env: DB_DEBUG=true)
tail -100 storage/logs/laravel.log | grep "query"
```

#### 500 Internal Server Error

```bash
# Check application logs
tail -50 storage/logs/laravel.log

# Check Nginx error log
tail -50 /var/log/nginx/error.log

# Clear caches and retry
php artisan config:clear
php artisan route:clear
```

### Debug Mode

For troubleshooting, temporarily enable debug mode:

```bash
# .env
APP_DEBUG=true
LOG_LEVEL=debug
```

> **Warning:** Never leave `APP_DEBUG=true` in production. It exposes sensitive information in error responses.

---

## 12. Maintenance Procedures

### Log Rotation

Configure logrotate to prevent disk space exhaustion:

`/etc/logrotate.d/subscription-engine`:

```
/var/www/subscription-engine/storage/logs/*.log {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    create 0664 www-data www-data
    sharedscripts
    postrotate
        systemctl reload php8.3-fpm > /dev/null 2>&1 || true
    endscript
}
```

### Cache Clearing

```bash
# Clear all caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Database Optimization

```bash
# Analyze table performance
mysql -u app_user -p -e "ANALYZE TABLE plans, plan_prices, subscriptions;" subscription_engine

# Optimize tables (reclaims space, defragments)
mysql -u app_user -p -e "OPTIMIZE TABLE plans, plan_prices, subscriptions;" subscription_engine
```

### Pruning Soft-Deleted Records

```bash
# Prune soft-deleted records older than 90 days
php artisan model:prune --model=App\Models\Plan --model=App\Models\Subscription
```

---

## 13. Runbook: Common Scenarios

### Scenario 1: User Reports Subscription Not Activated After Trial

**Symptoms:** User's subscription is still `trialing` after trial period ended.

**Diagnosis:**
```bash
# Check if scheduler ran
tail -20 storage/logs/scheduler-subscriptions.log

# Check for the specific subscription
php artisan tinker --execute="
\$sub = \App\Models\Subscription::find(SUBSCRIPTION_ID);
echo 'Status: ' . \$sub->status->value . PHP_EOL;
echo 'Trial ends: ' . \$sub->trial_ends_at . PHP_EOL;
"
```

**Resolution:**
```bash
# Manually process expired trials
php artisan subscriptions:process

# Or activate the specific subscription via the service
php artisan tinker --execute="
\$sub = \App\Models\Subscription::find(SUBSCRIPTION_ID);
app(\App\Services\SubscriptionLifecycleService::class)->handleTrialExpiration(\$sub);
"
```

### Scenario 2: User Stuck in Past Due After Payment

**Symptoms:** User made a payment but subscription is still `past_due`.

**Resolution:**
```bash
# Simulate payment success via API
curl -X POST https://api.yourdomain.com/api/v1/subscriptions/{id}/simulate-payment-success \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

### Scenario 3: High Number of Failed Queue Jobs

**Symptoms:** Queue workers failing repeatedly.

**Diagnosis:**
```bash
# List failed jobs
php artisan queue:failed

# View details of a failed job
php artisan queue:failed --ids
```

**Resolution:**
```bash
# Retry all failed jobs
php artisan queue:retry all

# If jobs keep failing, investigate the listener
tail -100 storage/logs/queue-worker.log
```

### Scenario 4: API Rate Limiting Too Aggressive

**Symptoms:** Legitimate users receiving 429 Too Many Requests.

**Resolution:**
```bash
# Increase rate limit in bootstrap/app.php
# Change Limit::perMinute(60) to Limit::perMinute(120)

# Clear config cache
php artisan config:clear
php artisan config:cache
```

### Scenario 5: Database Migration Failed Mid-Execution

**Symptoms:** Migration partially applied, application in inconsistent state.

**Resolution:**
```bash
# Check migration status
php artisan migrate:status

# Rollback the failed migration
php artisan migrate:rollback

# Fix the migration file and re-run
php artisan migrate --force
```

### Scenario 6: Disk Space Full Due to Logs

**Symptoms:** Application returning 500 errors, disk at 100%.

**Resolution:**
```bash
# Find large files
du -sh storage/logs/* | sort -rh | head -10

# Truncate the largest log file
truncate -s 0 storage/logs/laravel.log

# Set up logrotate (see Maintenance Procedures)
```

---

## Appendix: Command Reference

| Command | Purpose |
|---------|---------|
| `php artisan serve` | Start development server |
| `php artisan migrate --force` | Run database migrations |
| `php artisan migrate:fresh --seed` | Reset and seed database |
| `php artisan subscriptions:process` | Run subscription lifecycle processor |
| `php artisan subscriptions:process --dry-run` | Preview transitions without applying |
| `php artisan subscriptions:process --chunk-size=500` | Process with custom chunk size |
| `php artisan schedule:list` | List all scheduled commands |
| `php artisan schedule:run` | Run the scheduler (called by cron) |
| `php artisan route:list --path=api/v1` | List all API routes |
| `php artisan test` | Run all tests |
| `php artisan config:cache` | Cache configuration for production |
| `php artisan config:clear` | Clear configuration cache |
| `php artisan queue:failed` | List failed queue jobs |
| `php artisan queue:retry all` | Retry all failed queue jobs |
