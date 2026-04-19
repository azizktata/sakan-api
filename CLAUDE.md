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

### Database (11 migrations)
Key domain tables:
- **users** — extended with `google_id`, `provider`, `avatar`, `phone`, `role`
- **locations** — self-referencing (`parent_id`) for geographic hierarchy
- **properties** — listings with `transaction_type` (sale/rent), `status` (draft/published/sold/rented), `property_type`, geo coordinates, `location_id`
- **property_images** — references Cloudflare R2 URLs
- **amenities** + **property_amenities** — junction table
- **contacts** — buyer/renter inquiries

### Image Storage
Images are uploaded to **Cloudflare R2** (S3-compatible). Flow: client compresses → Laravel generates presigned URL → client uploads directly to R2.

### Controllers to Implement (see FEAT-PLAN.md)
- `AuthController` — register, login, logout, me
- `SocialAuthController` — Google OAuth
- `PropertyController` — CRUD, filtered listing
- `ContactController` — inquiries
- `AdminPropertyController` / `AdminUserController` — moderation

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
