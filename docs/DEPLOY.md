# Deploying Peeps

Laravel 12 + Livewire 3, MySQL, server-rendered. No websocket server required for v1 (chat polls).

## Production checklist
- PHP 8.2+, `composer install --no-dev --optimize-autoloader`
- `.env`: `APP_ENV=production`, `APP_DEBUG=false`, real `APP_KEY`, MySQL creds
- `php artisan migrate --force`
- `php artisan storage:link` (serves public trip covers)
- Assets: `npm ci && npm run build`
- Caches: `php artisan config:cache route:cache view:cache`

## Storage
- `public` disk → S3 (or public bucket) for trip/cover photos.
- `local` (private) disk → payment proofs; served only through `ProofController` (host-authorized). Never expose the proofs directory over the web.

## Queue & schedule
- Queue: `QUEUE_CONNECTION=redis`; run `php artisan queue:work` (supervisor).
- Scheduler cron (one line): `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`
  - runs `reservations:expire` hourly and `departures:complete` daily (auto-recaps).

## Later
- Realtime chat: add Laravel Reverb + Echo; the `messages` model and `ChatGate` are unchanged, only the transport swaps from `wire:poll`.
- In-app payments: a gateway plugs into the reservation state machine at `payment_submitted → confirmed`.
