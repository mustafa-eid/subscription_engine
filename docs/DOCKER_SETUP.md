# Docker Setup Guide

Quick and easy setup using Docker — no need to install PHP, MySQL, or Composer manually.

## Prerequisites

- [Docker](https://docs.docker.com/get-docker/) installed
- [Docker Compose](https://docs.docker.com/compose/install/) installed

## Quick Start

### 1. Clone the Repository

```bash
git clone <repository-url>
cd subscription-engine
```

### 2. Start All Services

```bash
docker-compose up -d
```

This starts:

| Service | Description | Port |
|---------|-------------|------|
| **app** | Main application (PHP-FPM + Nginx) | 8000 |
| **db** | MySQL 8.0 database | 3306 |
| **redis** | Redis for cache/sessions | 6379 |
| **worker** | Queue worker for background jobs | — |
| **scheduler** | Laravel scheduler for automated tasks | — |
| **phpmyadmin** | Database management (dev only) | 8080 |

### 3. Run Migrations & Seed Data

```bash
docker-compose exec app php artisan migrate --seed
```

### 4. Access the Application

- **API:** http://localhost:8000
- **phpMyAdmin:** http://localhost:8080

---

## Common Commands

### View Logs

```bash
# All services
docker-compose logs -f

# App only
docker-compose logs -f app
```

### Run Artisan Commands

```bash
docker-compose exec app php artisan <command>

# Examples:
docker-compose exec app php artisan migrate
docker-compose exec app php artisan db:seed
docker-compose exec app php artisan test
docker-compose exec app php artisan cache:clear
```

### Run Tests

```bash
docker-compose exec app php artisan test
```

### Stop Services

```bash
docker-compose down
```

### Rebuild the Image

```bash
docker-compose up -d --build
```

### Reset Everything

```bash
docker-compose down -v
docker-compose up -d
docker-compose exec app php artisan migrate --seed
```

---

## Environment Variables

Edit `.env` file if you need to change ports or credentials:

```env
APP_PORT=8000
DB_PORT=3306
REDIS_PORT=6379
PMA_PORT=8080
```

After editing, rebuild:

```bash
docker-compose up -d --build
```

---

## Troubleshooting

### Port Already in Use

If port 8000 is already in use, change `APP_PORT` in `.env`:

```env
APP_PORT=8081
```

Then rebuild:

```bash
docker-compose up -d --build
```

### Permission Issues

```bash
docker-compose exec app chmod -R 775 storage bootstrap/cache
docker-compose exec app chown -R www-data:www-data storage bootstrap/cache
```

### Database Connection Error

Make sure MySQL is ready before running migrations:

```bash
docker-compose logs db
# Wait for "ready for connections" message
docker-compose exec app php artisan migrate
```
