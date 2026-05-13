# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

SAKAN (سكن) is a halal/ethical real estate platform for Tunisia. This repo is the **Laravel 13 REST API** backend consumed by a separate Next.js frontend and future mobile apps.

## Commands

```bash
# First-time setup
composer setup          # install deps, generate key, migrate, build assets

# Development (runs 4 concurrent processes: server, queue, logs, vite)
composer dev

# Testing (uses SQLite :memory:, no DB setup required)
composer test

# Linting
composer run lint       # or: ./vendor/bin/pint

# Individual artisan commands
php artisan migrate
php artisan tinker
```

## Architecture

### Request Flow
```
Next.js / Mobile → Laravel API (routes/api.php) → Controllers → Eloquent Models → MySQL
```

Authentication uses **Laravel Sanctum** with API tokens stored in httpOnly cookies (BFF pattern for the web frontend). Google OAuth is handled via **Laravel Socialite**.

### Auth Strategy
- Email/password: register → token issued, stored in httpOnly cookie
- Google OAuth: redirect → callback → token issued
- Token expiry: 30 days
- Roles: `particulier` (individual), `agent` (real estate agent), `admin`
- Role middleware protects admin routes

### Database (20 migrations)
Key domain tables:
- **users** — extended with `google_id`, `provider`, `avatar`, `phone`, `role`
- **locations** — self-referencing (`parent_id`) for geographic hierarchy; `zone_score TINYINT` (1–5, 5=premium), `neighborhoods JSON` (array of strings); 131 rows (24 governorates + 107 cities)
- **properties** — listings with `transaction_type` (sale/rent), `status` (draft/published/sold/rented), `property_type`, geo coordinates, `location_id`
- **property_images** — references Cloudflare R2 URLs
- **amenities** + **property_amenities** — junction table
- **contacts** — buyer/renter inquiries
- **estimation_logs** — ML estimation requests; extended with `estimation_id` (UUID), `user_opinion` (too_high/correct/too_low), `feedback_at`

Analytics tables (Phase 1 Data Intelligence Platform):
- **property_views** — raw view events with `visitor_key`, `session_bucket`, `unique_key` (SHA-256, UNIQUE), `source`, `device`, `ip_hash`; Phase 2 additions: `view_id` (char 36, UUID returned to client), `country`, `city_geo` (from GeoIP), `duration_seconds`
- **property_stats_daily** — daily aggregates per property: `views_total`, `views_unique`, `contacts_count`, `conversion_rate`; unique on `(property_id, date)`
- **city_stats_daily** — daily aggregates per location: `views_total`, `properties_published`, `contacts_count`, `demand_supply_ratio`; unique on `(location_id, date)`

Analytics tables (Phase 2 Data Intelligence Platform):
- **user_sessions** — session lifecycle tracking; unique `session_token`; `visitor_key`, `device`, `started_at`, `last_seen_at`, `ended_at`, `duration_seconds`
- **visitor_identities** — anonymous-to-authenticated stitching; unique `(visitor_key, user_id)`
- **searches** — search event log; unique `search_id`; `filters` JSON; `location_id` extracted for indexing
- **market_insights_daily** — per-location daily market KPIs; unique `(location_id, date)`

### Image Storage
Images are uploaded via `POST /api/upload/image` — Laravel stores them server-side. Cloudflare R2 presigned upload is in `FEAT-PLAN.md` (spec only, not implemented).

### Controllers
- `AuthController` — register, login, logout, me; Phase 2: identity stitching in `login()` and `register()` (links visitor_key to user_id in `visitor_identities`)
- `SocialAuthController` — Google OAuth
- `PropertyController` — CRUD, filtered listing
- `ContactController` — inquiries
- `AdminPropertyController` / `AdminUserController` — moderation
- `EstimationController` — ML price estimation + `feedback()` method for user opinion (too_high/correct/too_low)
- `AnalyticsController` — `trackView()`, `propertyStats()`, `propertyTrend()`, `ownerSummary()` (owner-facing); Phase 2: `updateDuration()` (PATCH view duration), `trackSearch()` (search event logging)
- `SessionController` — Phase 2: `start()`, `ping()`, `end()` (session lifecycle, public routes)
- `Admin\AdminAnalyticsController` — `overview()`, `topProperties()`, `topCities()`, `conversionFunnel()`, `estimationDataset()` (admin-only); Phase 2: `marketInsights()`, `searchTrends()`, `sessions()`, `geoBreakdown()`

### Scheduled Commands
- `analytics:aggregate` — runs daily at 00:05; aggregates yesterday's `property_views` into `property_stats_daily` and `city_stats_daily`. Idempotent (uses UPSERT). Register via `routes/console.php`.
- `analytics:aggregate-market` — runs daily at 00:15 (after `analytics:aggregate`); aggregates `searches` + `property_views` + `properties` into `market_insights_daily`. Idempotent (uses UPSERT).

## GeoIP Setup (Phase 2)

Uses `geoip2/geoip2` PHP package with a local MaxMind GeoLite2-City database.

File location: `storage/geoip/GeoLite2-City.mmdb` (git-ignored, ~60MB)

Download steps:
1. Create a free account at maxmind.com
2. My Account → Download Files → GeoLite2 City → Download GZIP
3. Extract and place GeoLite2-City.mmdb at storage/geoip/GeoLite2-City.mmdb

If file is missing, GeoIpService returns null for country/city — tracking still works, geo fields are null.

## Key Files

| File | Purpose |
|------|---------|
| `FEAT-PLAN.md` | Full architecture spec with route list, controller examples, CORS config |
| `routes/api.php` | All API route definitions |
| `bootstrap/app.php` | Middleware registration, routing config |
| `config/sanctum.php` | Token expiry (30d), stateful domains |
| `config/services.php` | Google OAuth credentials |
| `database/migrations/` | All schema definitions |

## Environment Variables

Required beyond standard Laravel defaults:
```
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback

DB_CONNECTION=mysql
DB_DATABASE=sakan_db
DB_USERNAME=root
DB_PASSWORD=root
```

For image uploads (future):
```
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=auto
AWS_BUCKET=
AWS_ENDPOINT=   # Cloudflare R2 endpoint
```

## Testing

Tests use SQLite `:memory:` — no external DB needed. Run a single test:
```bash
php artisan test --filter TestClassName
# or
./vendor/bin/phpunit tests/Feature/ExampleTest.php
```
