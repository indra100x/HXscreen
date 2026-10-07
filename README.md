# HXscreen

Digital signage for venues. A web panel manages businesses, screens, playlists, and videos; an Android TV app pairs with a code and loops the assigned content fullscreen.

## Parts

- `laravel/` — web panel + API (Laravel 13, PHP 8.5, Inertia v3 + React, Fortify auth, Pest tests).
- `tv-app/` — Android TV player (ExoPlayer via Media3 1.8.x, minSdk 21). See `tv-app/README.md` for build details.
- `docker/` + `Dockerfile` + `docker-compose.yml` — production image (Apache + PHP, queue worker, scheduler) and deploy recipe.

## How it works

1. The TV shows a pairing code (`POST /api/screens/request-pairing-code`, polls `/api/device/claim`).
2. You pair it from the panel (code + business). The TV gets a device token and pulls `GET /api/device/content`.
3. Videos stream as progressive MP4 over HTTPS (R2 presigned URLs), looped fullscreen with ExoPlayer. Heartbeat every 30s.
4. Playback stats, audit activity, and per-business analytics land back in the panel.

## Branches

- `main` — multi-business edition. One deployment serves many businesses: dashboard lists every business you own or belong to, plus cross-business screens and storage overviews.
- `single-business` — resell edition. One deployment = one customer server = one venue:
  - No public registration (`/register` is 404). The login is seeded at install (see below).
  - First visit goes to a setup screen only when no venue exists yet; otherwise the dashboard redirects straight into the venue.
  - No business list, no cross-business screens/storage pages, no venue deletion.
  - Team tab: the owner creates teammate logins directly (name + email + password). Members manage content; only the owner manages the team and the venue.

## Install (single-business resell)

One package, deployed per customer on their server. Each deployment is fully independent (own database volume, storage volume, `APP_KEY`).

```bash
# Per customer: set these (e.g. in a .env file next to docker-compose.yml)
APP_KEY=<php artisan key:generate --show>
APP_URL=https://screens.customer.example
OWNER_NAME="Corner Shop"
OWNER_EMAIL=owner@customer.example
OWNER_PASSWORD=<at least 8 characters>
BUSINESS_NAME="Corner Shop"
R2_ACCESS_KEY_ID=...
R2_SECRET_ACCESS_KEY=...
R2_BUCKET=hxscreen-videos
R2_ENDPOINT=https://<account>.r2.cloudflarestorage.com

docker compose up -d
```

On every boot the entrypoint migrates the database and runs `php artisan app:ensure-owner` (idempotent): it creates or updates the `OWNER_EMAIL` login and ensures its venue exists. Leave `OWNER_*` unset for local development.

Storage: prefer a bucket in the customer's own Cloudflare account (their videos, their bill). The app reads all R2 settings from env, so no code changes per customer.

## Local development (panel)

```bash
cd laravel
composer install
npm install
php artisan migrate
npm run dev        # or: composer run dev
php artisan test --compact
```

## Verify

```bash
cd laravel
vendor/bin/pint --dirty --format agent
php artisan test --compact
npm run types:check && npm run check
npm run build
```
