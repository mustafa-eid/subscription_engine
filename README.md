# Subscription Management API

A production-ready, multi-currency subscription management API built with Laravel 13. Supports trial periods, flexible billing cycles, payment failure recovery with grace periods, and automated lifecycle processing via scheduled tasks.

---

## Table of Contents

- [Overview](#overview)
- [Subscription Lifecycle](#subscription-lifecycle)
- [Architecture & Design](#architecture--design)
- [Features](#features)
- [Installation](#installation)
- [Configuration](#configuration)
- [Scheduler / CRON](#scheduler--cron)
- [API Usage](#api-usage)
- [Health Checks](#health-checks)
- [Webhooks](#webhooks)
- [Testing](#testing)
- [Postman Collection](#postman-collection)
- [Notes](#notes)

---

## Overview

This API provides a complete subscription management system with the following capabilities:

- **Plan Management** — Create and manage subscription plans with dynamic pricing per currency (USD, AED, EGP) and billing cycle (Monthly, Yearly).
- **Trial Periods** — Plans can include configurable trial periods (in days). Users who subscribe to plans with trials start in a `trialing` state.
- **Payment Failure & Grace Period** — When a payment fails, the subscription moves to `past_due` with a 3-day grace period during which the user retains full access.
- **Automated Lifecycle Processing** — A daily scheduled command automatically transitions expired trials to active and cancels subscriptions whose grace periods have expired.
- **Event-Driven Architecture** — Every state transition dispatches domain events (`SubscriptionActivated`, `SubscriptionPastDue`, `SubscriptionCanceled`) for integration with external systems (email, analytics, webhooks).
- **Payment Gateway Abstraction** — Provider-agnostic payment processing with support for Stripe, Paddle, and custom gateways.
- **Audit Logging** — Complete subscription change history with request tracking and compliance support.
- **Health Monitoring** — Built-in health check endpoints for monitoring service status.

---

## Subscription Lifecycle

The subscription state machine follows this flow:

```
                    ┌──────────────┐
                    │   trialing   │ ← New subscription with trial
                    └──────┬───────┘
                           │ Trial expires
                           ▼
┌──────────────┐    ┌──────────────┐
│   canceled   │◄───│    active    │ ← Trial ends OR subscribe without trial
└──────────────┘    └──────┬───────┘
      ▲                   │ Payment fails
      │                   ▼
      │            ┌──────────────┐
      │            │   past_due   │ ← Grace period starts (3 days)
      │            └──────┬───────┘
      │                   │
      ├───────────────────┤
      │                   │
  Grace expires    Payment succeeds
      │                   │
      ▼                   ▼
┌──────────────┐    ┌──────────────┐
│   canceled   │    │    active    │
└──────────────┘    └──────────────┘
```

### Access Rules

A user **has access** to subscription features when:

| Status | Has Access | Condition |
|--------|:----------:|-----------|
| `active` | ✅ Yes | Normal paid access |
| `trialing` | ✅ Yes | Trial period is still active |
| `past_due` | ✅ Yes | Still within the 3-day grace period |
| `past_due` | ❌ No | Grace period has expired |
| `canceled` | ❌ No | Subscription has ended |

---

## Architecture & Design

### Layered Architecture

```
┌─────────────────────────────────────────────────┐
│                  API Layer                       │
│  Controllers → Form Requests → API Resources     │
├─────────────────────────────────────────────────┤
│                Service Layer                     │
│  SubscriptionLifecycleService (business logic)   │
├─────────────────────────────────────────────────┤
│             Repository Layer                     │
│  Interfaces → Eloquent Implementations           │
├─────────────────────────────────────────────────┤
│              Database Layer                      │
│  Models → Migrations → Factories → Seeders       │
└─────────────────────────────────────────────────┘
```

### Key Design Decisions

| Pattern | Implementation |
|---------|---------------|
| **Repository Pattern** | `PlanRepositoryInterface` / `SubscriptionRepositoryInterface` with Eloquent implementations, bound via `AppServiceProvider` |
| **Service Layer** | `SubscriptionLifecycleService` — single source of truth for all subscription state transitions |
| **Form Requests** | Input validation encapsulated in dedicated request classes (`StorePlanRequest`, `SubscribeRequest`, etc.) |
| **API Resources** | `PlanResource`, `PlanPriceResource`, `SubscriptionResource` for consistent JSON serialization |
| **Enums** | `Currency`, `BillingCycle`, `SubscriptionStatus` — type-safe constants with helper methods |
| **Domain Events** | `SubscriptionActivated`, `SubscriptionPastDue`, `SubscriptionCanceled` — dispatched on every state change |
| **Idempotency** | Application-level duplicate check + database-level unique constraint on active subscriptions prevents race conditions |
| **ApiResponse Trait** | All controllers use `ApiResponse` for consistent `{ status, message, data, meta }` JSON responses |
| **State Transition Guards** | `ALLOWED_TRANSITIONS` matrix in the `Subscription` model prevents invalid state changes |
| **Authorization Policies** | `SubscriptionPolicy` and `PlanPolicy` for fine-grained access control |
| **Audit Trail** | `SubscriptionAuditLog` tracks all state changes with metadata |

---

## Features

### Core Features
- ✅ Multi-currency pricing (USD, AED, EGP)
- ✅ Flexible billing cycles (Monthly, Yearly)
- ✅ Configurable trial periods
- ✅ Payment failure recovery with grace period
- ✅ Automated subscription lifecycle processing
- ✅ Event-driven architecture
- ✅ Structured logging

### Security & Authorization
- ✅ Policy-based authorization (`SubscriptionPolicy`, `PlanPolicy`)
- ✅ Rate limiting (endpoint-specific limits)
- ✅ Webhook signature verification
- ✅ Request ID tracking for distributed tracing

### Monitoring & Observability
- ✅ Health check endpoints (`/api/health`, `/api/health/detailed`)
- ✅ Subscription audit logging
- ✅ Service status monitoring (database, cache, queue)
- ✅ Response time tracking

### Payment Integration
- ✅ Payment gateway abstraction layer
- ✅ Stripe integration (stub for production)
- ✅ Webhook handling for payment confirmations
- ✅ Easy to add custom payment providers

### Deployment & DevOps
- ✅ Environment-based configuration
- ✅ Production-ready configuration

---

## Installation

Choose one of the setup methods below:

- **[Docker Setup](DOCKER_SETUP.md)** — Recommended (no manual PHP/MySQL installation needed)
- **[Local Setup](LOCAL_SETUP.md)** — Using XAMPP, MAMP, Laragon, or any local PHP environment

### Quick Start

```bash
# One-command setup (installs dependencies, creates .env, generates key, migrates & seeds)
composer setup

# Start the server
php artisan serve
```

Open: http://localhost:8000

### Manual Setup

```bash
# 1. Install dependencies
composer install

# 2. Configure environment
cp .env.example .env
php artisan key:generate

# 3. Configure your MySQL database in .env

# 4. Create database
mysql -u root -p -e "CREATE DATABASE subscription_engine;"

# 5. Run migrations and seed demo data
php artisan migrate --seed

# 6. Start the server
php artisan serve
```

The seeder creates 3 demo plans (Starter, Professional, Enterprise) with pricing in USD, AED, and EGP, plus a test user for authenticated endpoint testing.

After seeding, you can test authenticated endpoints with:

| Field | Value |
|-------|-------|
| **Email** | `test@example.com` |
| **Password** | `TestPass123` |

Or register a new user via the API:

```bash
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"John","email":"john@example.com","password":"TestPass123","password_confirmation":"TestPass123"}'
```

---

## Configuration

All business rules are centralized in `config/subscriptions.php`:

### Key Configuration Options

| Config Key | Default | Environment Variable | Description |
|------------|---------|---------------------|-------------|
| `grace_period_days` | 3 | `SUBSCRIPTION_GRACE_PERIOD_DAYS` | Days before canceled after payment failure |
| `scheduler.chunk_size` | 100 | `SUBSCRIPTION_SCHEDULER_CHUNK_SIZE` | Batch size for scheduler |
| `payment.default_driver` | stripe | `PAYMENT_DRIVER` | Default payment gateway |
| `audit.enabled` | true | `SUBSCRIPTION_AUDIT_ENABLED` | Enable audit logging |
| `audit.retention_days` | 365 | `SUBSCRIPTION_AUDIT_RETENTION_DAYS` | Audit log retention |
| `timezone` | UTC | `SUBSCRIPTION_TIMEZONE` | Application timezone |

### Rate Limiting

| Endpoint | Limit | Environment Variables |
|----------|-------|----------------------|
| Default API | 60/min | — |
| Subscribe | 10/min | `SUBSCRIBE_RATE_LIMIT_ATTEMPTS`, `SUBSCRIBE_RATE_LIMIT_DECAY` |
| Cancel | 5/min | `CANCEL_RATE_LIMIT_ATTEMPTS`, `CANCEL_RATE_LIMIT_DECAY` |
| Webhook | 100/min | `WEBHOOK_RATE_LIMIT_ATTEMPTS`, `WEBHOOK_RATE_LIMIT_DECAY` |

---

## Scheduler / CRON

The system includes a daily scheduled command that processes subscription lifecycle transitions automatically.

### Command

```bash
php artisan subscriptions:process --chunk-size=100
```

**Options:**

| Option | Default | Description |
|--------|---------|-------------|
| `--chunk-size` | `100` | Number of subscriptions to process per database chunk |
| `--dry-run` | `false` | Preview transitions without applying them |

### What It Does

1. **Expired Trials** — Finds subscriptions where `trial_ends_at` has passed and status is still `trialing`. Transitions them to `active`.
2. **Expired Grace Periods** — Finds subscriptions where `grace_period_ends_at` has passed and status is `past_due`. Transitions them to `canceled`.

### Server CRON Entry

Add this to your server's crontab to run the scheduler every minute:

```cron
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

The command itself is scheduled to run daily at midnight UTC with `withoutOverlapping()` and `onOneServer()` guards.

---

## API Usage

### Base URL

```
/api/v1/
```

### Authentication

Protected endpoints require a Sanctum token. Include it in the `Authorization` header:

```
Authorization: Bearer {token}
```

### Response Format

All responses follow a consistent structure via the `ApiResponse` trait:

**Success:**
```json
{
  "status": "success",
  "message": "Plans retrieved successfully.",
  "data": { ... }
}
```

**Paginated:**
```json
{
  "status": "success",
  "message": "Plans retrieved successfully.",
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 10,
    "total": 25
  }
}
```

**Error:**
```json
{
  "status": "error",
  "message": "User (ID: 1) already has an active subscription.",
  "error": "already_subscribed"
}
```

### Endpoints

#### Health Checks

| Method | Endpoint | Auth | Description |
|--------|----------|:----:|-------------|
| `GET` | `/api/health` | — | Basic health check |
| `GET` | `/api/health/detailed` | — | Detailed health check with metrics |

#### Authentication

| Method | Endpoint | Auth | Description |
|--------|----------|:----:|-------------|
| `POST` | `/api/v1/auth/register` | — | Register a new user and return token |
| `POST` | `/api/v1/auth/login` | — | Login and return token |

**Register Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "TestPass123",
  "password_confirmation": "TestPass123"
}
```

**Login Request Body:**
```json
{
  "email": "test@example.com",
  "password": "TestPass123"
}
```

**Response (both endpoints):**
```json
{
  "status": "success",
  "message": "...",
  "data": {
    "user": { "id": 1, "name": "John Doe", "email": "john@example.com" },
    "token": "1|abc123...",
    "token_type": "Bearer"
  }
}
```

#### Plans

| Method | Endpoint | Auth | Description |
|--------|----------|:----:|-------------|
| `GET` | `/api/v1/plans` | — | List active plans (paginated, default 10/page) |
| `GET` | `/api/v1/plans/{id}` | — | Get a single plan with prices |
| `POST` | `/api/v1/plans` | ✅ | Create a new plan with prices |
| `PUT` | `/api/v1/plans/{id}` | ✅ | Update a plan and/or its prices |
| `DELETE` | `/api/v1/plans/{id}` | ✅ | Soft-delete a plan |

**Query Parameters (GET /plans):**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `per_page` | int | `10` | Items per page (max: 50) |
| `page` | int | `1` | Page number |

**Create Plan Request Body:**
```json
{
  "name": "Premium Plan",
  "description": "A premium subscription plan",
  "trial_days": 14,
  "is_active": true,
  "prices": [
    { "currency": "usd", "billing_cycle": "monthly", "price": 29.99 },
    { "currency": "usd", "billing_cycle": "yearly", "price": 299.99 },
    { "currency": "aed", "billing_cycle": "monthly", "price": 109.99 }
  ]
}
```

#### Subscriptions

| Method | Endpoint | Auth | Description |
|--------|----------|:----:|-------------|
| `GET` | `/api/v1/subscriptions` | ✅ | List authenticated user's subscriptions |
| `POST` | `/api/v1/subscriptions/subscribe` | ✅ | Subscribe to a plan |
| `POST` | `/api/v1/subscriptions/{id}/cancel` | ✅ | Cancel a subscription |
| `POST` | `/api/v1/subscriptions/{id}/simulate-payment-success` | ✅ | Simulate payment recovery (dev) |
| `POST` | `/api/v1/subscriptions/{id}/simulate-payment-failure` | ✅ | Simulate payment failure (dev) |

**Subscribe Request Body:**
```json
{
  "plan_id": 1,
  "currency": "usd",
  "billing_cycle": "monthly"
}
```

**Subscribe Response (201 Created):**
```json
{
  "status": "success",
  "message": "Subscription created successfully.",
  "data": {
    "id": 1,
    "user_id": 1,
    "plan": { ... },
    "status": "trialing",
    "currency": "usd",
    "billing_cycle": "monthly",
    "price": "9.99",
    "trial_ends_at": "2026-04-18T00:00:00+00:00",
    "starts_at": "2026-04-04T00:00:00+00:00",
    "ends_at": null,
    "grace_period_ends_at": null,
    "has_access": true,
    "is_in_trial": true,
    "is_in_grace_period": false
  }
}
```

### Idempotency

The `subscribe` endpoint is idempotent:

- **Application-level:** Checks for an existing active/trialing/past_due subscription before creating a new one.
- **Database-level:** A unique constraint on the `active_user_id` generated column prevents duplicate active subscriptions even under concurrent requests.
- **Response:** Duplicate attempts return `409 Conflict` with `"error": "already_subscribed"`.

### Error Codes

| HTTP Status | Error Key | Description |
|:-----------:|-----------|-------------|
| `401` | — | Unauthenticated (missing/invalid token) |
| `403` | — | Forbidden (user doesn't own the subscription) |
| `404` | `not_found` | Resource not found |
| `404` | `plan_not_found` | Plan does not exist |
| `404` | `price_not_found` | No price for the given currency/billing cycle |
| `409` | `already_subscribed` | User already has an active subscription |
| `422` | — | Validation error |
| `422` | `invalid_subscription_state` | Cannot perform operation in current subscription state |

---

## Health Checks

### Basic Health Check

```bash
GET /api/health
```

**Response:**
```json
{
  "status": "ok",
  "timestamp": "2026-04-06T00:00:00+00:00",
  "version": "1.0.0",
  "environment": "production",
  "timezone": "UTC",
  "services": {
    "database": { "status": "ok", "response_time_ms": 2.5, "connection": "mysql" },
    "cache": { "status": "ok", "response_time_ms": 1.2, "driver": "redis" },
    "queue": { "status": "ok", "response_time_ms": 0.8, "driver": "database", "pending_jobs": 0 }
  }
}
```

### Detailed Health Check

```bash
GET /api/health/detailed
```

Includes additional metrics:
- Subscription counts (active, past_due, canceled)
- Configuration values
- Service response times

---

## Webhooks

### Payment Webhook

```bash
POST /api/webhooks/payment
```

Handles payment gateway webhook events with signature verification.

**Headers:**
```
X-Webhook-Signature: {signature}
```

**Supported Events:**
- `payment.succeeded` - Auto-activate subscriptions
- `payment.failed` - Move to past_due status
- `subscription.canceled` - Handle external cancellations
- `subscription.updated` - Sync changes
- `subscription.trial_ending` - Send notifications

---

## Testing

The project includes **72 feature tests** with **217 assertions** covering the entire subscription lifecycle, API endpoints, event dispatch, logging, idempotency, and batch processing.

```bash
# Run all tests
php artisan test

# Run a specific test file
php artisan test tests/Feature/SubscriptionLifecycleTest.php

# Run with coverage (if Xdebug is enabled)
php artisan test --coverage
```

### Test Coverage Summary

| Test File | Tests | Coverage Area |
|-----------|:-----:|---------------|
| `SubscriptionLifecycleTest.php` | 36 | State transitions, events, logging, access logic, idempotency, batch processing |
| `ApiEndpointsTest.php` | 20 | All API endpoints, authentication, validation, response format |
| `ProcessSubscriptionsTest.php` | 14 | Scheduler command, trial expiration, grace period, dry-run, mixed scenarios |

---

## Postman Collection

A ready-to-import Postman collection is available at:

```
docs/SubscriptionAPI.postman_collection.json
```

It includes all endpoints with example request bodies, headers, query parameters, and response examples.

**To import:**
1. Open Postman → Import
2. Select `docs/SubscriptionAPI.postman_collection.json`
3. Set the `base_url` variable to your server URL (e.g., `http://localhost:8000`)
4. Set the `bearer_token` variable with a valid Sanctum token for authenticated requests

---

## Notes

### Rate Limiting

All API routes are rate-limited with endpoint-specific limits:
- Default API: **60 requests per minute**
- Subscribe: **10 requests per minute**
- Cancel: **5 requests per minute**
- Webhook: **100 requests per minute**

### Events

Every subscription state change dispatches a domain event:

| Event | When Dispatched |
|-------|----------------|
| `SubscriptionActivated` | New subscription (no trial), trial expires, payment recovery |
| `SubscriptionPastDue` | Payment fails |
| `SubscriptionCanceled` | User cancels, grace period expires |

Listen to these events to integrate with external systems (email notifications, analytics, webhooks).

### Structured Logging

All lifecycle transitions are logged with structured context:

```json
{
  "message": "Trial expired — subscription activated.",
  "context": {
    "subscription_id": 1,
    "user_id": 1,
    "old_status": "trialing",
    "new_status": "active",
    "processed_at": "2026-04-04T00:00:00+00:00"
  }
}
```

Log entries are written to `storage/logs/laravel.log`. The scheduler also appends output to `storage/logs/scheduler-subscriptions.log`.

### Audit Logging

All subscription state changes are logged to the `subscription_audit_logs` table with:
- Event type and old/new status
- Metadata (plan details, pricing, etc.)
- Triggered by (user, system, scheduler, webhook)
- Request ID for tracing
- IP address and user agent
- Timestamp

### Database Schema

```
plans
├── id
├── name
├── description
├── trial_days
├── is_active
├── timestamps + soft_deletes
└── index(is_active)

plan_prices
├── id
├── plan_id (FK → plans)
├── currency (enum: usd, aed, egp)
├── billing_cycle (enum: monthly, yearly)
├── price (decimal)
├── timestamps
├── unique(plan_id, currency, billing_cycle)
└── index(currency, billing_cycle)

subscriptions
├── id
├── user_id (FK → users)
├── plan_id (FK → plans)
├── status (enum: trialing, active, past_due, canceled)
├── currency
├── billing_cycle
├── price
├── trial_ends_at
├── starts_at
├── ends_at
├── grace_period_ends_at
├── timestamps + soft_deletes
├── unique(active_user_id) — idempotency constraint
├── index(user_id, status)
├── index(status, trial_ends_at)
├── index(status, grace_period_ends_at)
└── index(user_id, created_at)

subscription_audit_logs
├── id
├── subscription_id (FK → subscriptions)
├── user_id (FK → users)
├── event_type
├── old_status
├── new_status
├── metadata (JSON)
├── triggered_by
├── request_id
├── ip_address
├── user_agent
├── occurred_at
└── timestamps
```

### Timezone Handling

All timestamps are stored in UTC. Use the verification command:

```bash
# Check timezone consistency
php artisan timezones:verify

# Fix non-UTC timestamps
php artisan timezones:verify --fix
```
