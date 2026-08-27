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
4. Enable Admin API scopes that match `SHOPIFY_API_SCOPES` (starter defaults include `read_products`, `write_products`, `read_metaobjects`, `write_metaobjects`). Reinstall/re-auth after changing scopes.
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
docker compose exec app php vendor/bin/phpunit --filter "ShopifyGraphQlExceptionTest|ShopifyAdminApiTest|ShopifyApiEndpointsTest|ShopifyWebhookTest|ShopifyBilling|ShopifyMetafield|ShopifyMetaobject|ShopifyEmbedded"
```

## Useful routes

| Route | Purpose |
| --- | --- |
| `/` | Inertia Dashboard (requires `verify.shopify`) |
| `/authenticate` | Shopify OAuth install/callback (`?shop=` required) |
| `/api/shop` | JSON shop GraphQL result |
| `/api/products` | JSON products GraphQL result |
| `/api/metafields` | List metafields for an owner (`owner_id` required) |
| `POST /api/metafields` | Create/update a metafield (`metafieldsSet`) |
| `/api/metaobjects?type=` | List metaobjects by type |
| `/api/metaobjects/{id}` | Fetch a metaobject |
| `POST /api/metaobjects` | Create a metaobject entry |
| `PATCH /api/metaobjects/{id}` | Update a metaobject entry |
| `POST /webhook/{type}` | Shopify webhooks (`auth.webhook` HMAC) |
| `/plans` | Inertia billing UI (app plans + status) |
| `/billing/{plan}` | Package: start Shopify charge confirmation |
| `/billing/process/{plan}` | Package: billing confirmation callback |

## Webhooks + queue

- Topics/URLs: `config/shopify-app.php` → `webhooks` (starter registers `APP_UNINSTALLED`)
- HMAC verified by package middleware `auth.webhook`
- Jobs land on `QUEUE_CONNECTION=database`; Compose service `queue` runs `php artisan queue:work`
- Verify locally: `docker compose exec app php artisan shopify:verify-webhooks --process`

## Billing

- Plan definitions: `src/config/shopify-plans.php` (Free freemium + Basic/Pro placeholders)
- Sync paid plans into package `plans` table: `docker compose exec app php artisan db:seed --class=ShopifyPlanSeeder`
- App service: `App\Services\Shopify\ShopifyBillingService`
- UI: Inertia `Billing` page at `/plans`
- Subscribe hand-off: app → package `/billing/{plan}` → Shopify confirmation → `/billing/process/{plan}`
- Enable with `SHOPIFY_BILLING_ENABLED=true` and `SHOPIFY_BILLING_FREEMIUM_ENABLED=true`
- Development charges: keep `SHOPIFY_BILLING_TEST_CHARGES=true`
- Paid feature check: `$billing->hasActivePaidPlan($shop)`
- Tests mock package actions — they never call live Shopify billing APIs

### Add a new paid plan

1. Add an entry under `paid` in `config/shopify-plans.php`
2. Re-run `ShopifyPlanSeeder`
3. Refresh `/plans` — React reads plans from the backend only

## Metafields & Metaobjects

Shopify custom data for apps:

- **Metafields** — key/value extras attached to resources (Shop, Product, Customer, …)
- **Metaobjects** — reusable structured entries defined by a MetaobjectDefinition (type + fields)

### Service layer

- `App\Services\Shopify\ShopifyMetafieldService` — list/get/set via Admin GraphQL (`HasMetafields` + `metafieldsSet`)
- `App\Services\Shopify\ShopifyMetaobjectService` — list/find/create/update (`metaobjects`, `metaobjectCreate`, `metaobjectUpdate`)
- Both reuse `ShopifyAdminApi::graph()` and throw `ShopifyGraphQlException` (including mutation `userErrors` as `invalid_input` / HTTP 422)

Example:

```php
$metafields = app(ShopifyMetafieldService::class)->forShop($shop);
$metafields->listForOwner($shopGid, 'custom');
$metafields->setOne($productGid, 'custom', 'material', 'cotton');

$metaobjects = app(ShopifyMetaobjectService::class)->forShop($shop);
$metaobjects->list('size_chart');
$metaobjects->create([
    'type' => 'size_chart',
    'handle' => 'winter',
    'fields' => [['key' => 'title', 'value' => 'Winter']],
]);
```

### Scopes

Update Partners + `SHOPIFY_API_SCOPES`, then reinstall/re-auth:

- Product metafields: `read_products` / `write_products` (already required by the starter)
- Metaobject entries: `read_metaobjects` / `write_metaobjects`
- Creating definitions (not covered by this starter): `read_metaobject_definitions` / `write_metaobject_definitions`

### GraphQL notes

- Prefer GraphQL variables (services never interpolate secrets into documents)
- `metafieldsSet` creates or updates by owner + namespace + key
- Metaobject `create` requires an existing definition for `type`
- Prefer `fields` on update for partial patches; `values` replaces the full map
- Dashboard shows a small shop-metafields demo only; use the `/api/*` routes for full CRUD

### Tests

```bash
docker compose exec app php artisan test --filter="ShopifyMetafield|ShopifyMetaobject"
```

Mocks `BasicShopifyAPI` / services — no live Shopify calls.

## Normal developer workflow

1. `git clone` → `cp .env.example .env` → `cp src/.env.example src/.env`
2. Fill Shopify credentials + tunnel URLs in `src/.env`
3. `docker compose up -d --build`
4. `docker compose exec app composer install`
5. `docker compose exec app php artisan key:generate`
6. `docker compose exec app php artisan migrate`
7. `docker compose exec app php artisan db:seed --class=ShopifyPlanSeeder`
8. `cd src && npm install && npm run build`
9. Start HTTPS tunnel → align Partners URLs + `APP_URL` / `SHOPIFY_APP_URL`
10. Install app on a development store
11. Confirm Dashboard loads shop + products; open `/plans` for billing
12. `docker compose exec app php artisan test`
13. Build features on top of `ShopifyAdminApi` / `ShopifyBillingService` / metafield & metaobject services

## Architecture notes

- Shop model: `App\Models\User` implements package `ShopModel` (offline token stored in `password`; do not cast it as hashed).
- GraphQL client path: `$shop->api()->graph(...)` via `gnikyt/basic-shopify-api`.
- Reusable services: `ShopifyAdminApi`, `ShopifyBillingService`, `ShopifyMetafieldService`, `ShopifyMetaobjectService`.
- Errors: `App\Exceptions\ShopifyGraphQlException`, `App\Exceptions\ShopifyBillingException`.
- Billing tables/models: package `plans` + `charges` (do not duplicate).

## Embedded App Bridge

- CDN App Bridge (no npm App Bridge package): loaded in `resources/views/app.blade.php`
- Public Client ID only via `<meta name="shopify-api-key">` (never the API secret)
- Frontend module: `resources/js/Shopify/` (`appBridge`, `session`, `navigation`, `context`)
- Layout: `resources/js/Layouts/AppLayout.jsx` (Dashboard / Billing nav)
- Inertia XHR attaches `Authorization: Bearer <session token>` from `shopify.idToken()`
- Shared Inertia props: `shopify.apiKey`, `shopify.shopDomain`, `shopify.host`, `shopify.embedded`
- Prefer `SHOPIFY_FRONTEND_TYPE=SPA` for Inertia (see `src/.env.example`)
- iframe CSP remains `App\Http\Middleware\IframeProtection` (`frame-ancestors` for the shop + admin.shopify.com)
- OAuth (`/authenticate`) and package billing (`/billing/*`) stay full-page flows

## Status

- [x] Docker + Nginx + MySQL + Redis
- [x] React + Inertia + Vite
- [x] Shopify OAuth (expiring offline tokens)
- [x] Admin GraphQL (shop + products)
- [x] Dashboard + API endpoints + tests
- [x] Webhooks (APP_UNINSTALLED + HMAC + queue worker)
- [x] Billing foundation (plans UI + package charge flow)
- [x] Embedded App Bridge (CDN + Inertia session tokens)
- [x] Metafields & Metaobjects (GraphQL services + API demo endpoints)
