# Shopify Laravel Starter

Reusable internal starter for building Shopify apps with:

- Laravel (PHP) + MySQL
- Docker + Nginx
- `kyon147/laravel-shopify` + `gnikyt/basic-shopify-api`
- Inertia + React + Vite
- HTTPS tunnel (ngrok or Cloudflare Tunnel)

This is a **template**, not a business-specific app. Clone it, configure env vars, connect a development store, and start building.

## Prerequisites

- Docker Desktop (or Docker Engine + Compose)
- Node.js 22+ (for Vite build/dev on the host; optional if using the `node` container)
- Composer is available inside the `app` container
- A Shopify Partner account + development store
- An HTTPS tunnel client (`ngrok` or Cloudflare Tunnel)

## Repository layout

| Path | Purpose |
| --- | --- |
| `docker-compose.yml` | App, Nginx, MySQL, Redis, Node |
| `.env` (repo root) | Compose DB credentials (see `.env.example`) |
| `src/` | Laravel application |
| `src/.env` | Laravel + Shopify credentials (see `src/.env.example`) |
| `docker/` | PHP/Nginx image config |

## Quick start

```bash
# 1) Clone
git clone <repo-url> shopify-laravel-starter
cd shopify-laravel-starter

# 2) Root Compose env
cp .env.example .env

# 3) Laravel env
cp src/.env.example src/.env

# 4) Start containers
docker compose up -d --build

# 5) PHP dependencies (first run)
docker compose exec app composer install

# 6) App key
docker compose exec app php artisan key:generate

# 7) Migrate (includes Shopify package tables)
docker compose exec app php artisan migrate

# 8) JS dependencies + production build (on host, from src/)
cd src
npm install
npm run build
cd ..
```

App (HTTP): [http://localhost](http://localhost)

## Environment configuration

### Root `.env` (Docker Compose)

Used only by Compose for MySQL bootstrap:

- `COMPOSE_PROJECT_NAME`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `MYSQL_ROOT_PASSWORD`

### `src/.env` (Laravel)

Required Shopify keys (placeholders only in `src/.env.example`):

- `APP_URL` — HTTPS tunnel URL (no trailing slash)
- `SHOPIFY_API_KEY` — Partners Client ID
- `SHOPIFY_API_SECRET` — Partners Client Secret
- `SHOPIFY_APP_NAME`
- `SHOPIFY_APP_URL` — same as `APP_URL`
- `SHOPIFY_API_VERSION`
- `SHOPIFY_API_SCOPES` — must match Partners Admin API scopes
- `SHOPIFY_EXPIRING_OFFLINE_TOKENS=true` — required for current Admin API offline tokens

Docker network hostnames already set in `src/.env.example`:

- `DB_HOST=mysql`
- `REDIS_HOST=redis`

Never commit real `src/.env` or root `.env` secrets.

## Shopify Partner app setup

1. Create an app in Shopify Partners / Dev Dashboard.
2. Set **App URL** to your tunnel URL, e.g. `https://your-tunnel-subdomain.ngrok-free.dev`
3. Set **Allowed redirection URL(s)** to:
   `https://your-tunnel-subdomain.ngrok-free.dev/authenticate`
4. Enable Admin API scopes that match `SHOPIFY_API_SCOPES` (at minimum `read_products`, `write_products` for the starter Dashboard).
5. Copy Client ID / Secret into `src/.env` as `SHOPIFY_API_KEY` / `SHOPIFY_API_SECRET`.
6. Keep `APP_URL` and `SHOPIFY_APP_URL` equal to the tunnel URL (no trailing slash).

## HTTPS tunnel

Shopify requires HTTPS for app URLs.

```bash
# Example with ngrok (forwards to local Nginx on port 80)
ngrok http 80
```

Then update `src/.env`:

```env
APP_URL=https://your-tunnel-subdomain.ngrok-free.dev
SHOPIFY_APP_URL=https://your-tunnel-subdomain.ngrok-free.dev
```

Clear config cache after changing env:

```bash
docker compose exec app php artisan config:clear
```

Update Partners App URL + redirect URLs whenever the tunnel hostname changes.

## Install the app on a development store

1. Ensure Docker is up and the tunnel is running.
2. From Shopify Admin (dev store), install/open the app, **or** visit:
   `https://your-tunnel-subdomain.ngrok-free.dev/authenticate?shop=YOUR_STORE.myshopify.com`
3. Complete OAuth once (do not refresh the `?code=` callback URL).
4. You should land on the Dashboard (`/`) after auth.

## Verify GraphQL (optional CLI)

```bash
docker compose exec app php artisan shopify:graphql-ping
docker compose exec app php artisan shopify:shop
docker compose exec app php artisan shopify:products --first=10
```

## Frontend (Vite)

From `src/`:

```bash
npm install
npm run build   # production assets for Docker/Nginx
npm run dev     # Vite HMR (port 5173) when iterating on React
npm run lint
```

## Tests

```bash
docker compose exec app php artisan test
```

Or PHPUnit filters used during Milestone 6:

```bash
docker compose exec app php vendor/bin/phpunit --filter "ShopifyGraphQlExceptionTest|ShopifyAdminApiTest|ShopifyApiEndpointsTest|ShopifyWebhookTest"
```

## Useful routes

| Route | Purpose |
| --- | --- |
| `/` | Inertia Dashboard (requires `verify.shopify`) |
| `/authenticate` | Shopify OAuth install/callback (`?shop=` required) |
| `/api/shop` | JSON shop GraphQL result |
| `/api/products` | JSON products GraphQL result |
| `POST /webhook/{type}` | Shopify webhooks (`auth.webhook` HMAC) |

## Webhooks + queue

- Topics/URLs: `config/shopify-app.php` → `webhooks` (starter registers `APP_UNINSTALLED`)
- HMAC verified by package middleware `auth.webhook`
- Jobs land on `QUEUE_CONNECTION=database`; Compose service `queue` runs `php artisan queue:work`
- Verify locally: `docker compose exec app php artisan shopify:verify-webhooks --process`

## Normal developer workflow

1. `git clone` → `cp .env.example .env` → `cp src/.env.example src/.env`
2. Fill Shopify credentials + tunnel URLs in `src/.env`
3. `docker compose up -d --build`
4. `docker compose exec app composer install`
5. `docker compose exec app php artisan key:generate`
6. `docker compose exec app php artisan migrate`
7. `cd src && npm install && npm run build`
8. Start HTTPS tunnel → align Partners URLs + `APP_URL` / `SHOPIFY_APP_URL`
9. Install app on a development store
10. Confirm Dashboard loads shop + products
11. `docker compose exec app php artisan test`
12. Build features on top of `App\Services\Shopify\ShopifyAdminApi`

## Architecture notes

- Shop model: `App\Models\User` implements package `ShopModel` (offline token stored in `password`; do not cast it as hashed).
- GraphQL client path: `$shop->api()->graph(...)` via `gnikyt/basic-shopify-api`.
- Reusable service: `App\Services\Shopify\ShopifyAdminApi`.
- Errors: `App\Exceptions\ShopifyGraphQlException`.

## Status

- [x] Docker + Nginx + MySQL + Redis
- [x] React + Inertia + Vite
- [x] Shopify OAuth (expiring offline tokens)
- [x] Admin GraphQL (shop + products)
- [x] Dashboard + API endpoints + tests
- [x] Webhooks (APP_UNINSTALLED + HMAC + queue worker)
