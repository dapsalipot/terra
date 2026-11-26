# Deploying Tarana

Laravel 12 + Livewire 3, MySQL, server-rendered. No websocket server required for v1 (chat polls).

## Production checklist
- PHP 8.2+, `composer install --no-dev --optimize-autoloader`
- `.env`: `APP_ENV=production`, `APP_DEBUG=false`, real `APP_KEY`, `APP_URL` (the public https address, see below), MySQL creds
- `php artisan migrate --force`
- `php artisan storage:link` (serves public trip covers)
- Assets: `npm ci && npm run build`
- Caches: `php artisan config:cache route:cache view:cache`

## Public address, https and proxies
Links that leave the site are absolute addresses this app builds: the `og:url` and `og:image` tags and the canonical link that previews and search engines read, the Copy and Facebook buttons in a share panel, and the QR codes a host downloads for a trip or their page. Each takes its scheme and host from the request it is answering, or from `APP_URL` where there is no request (queued mail, the scheduler). Set this up before the first link goes out: Facebook and Messenger cache a preview, and a printed QR keeps its address for good.

- `APP_URL` is the public https address, for example `https://tarana.example`, with no trailing slash. The `http://localhost` in `.env.example` is for development only.
- Behind a load balancer or reverse proxy that ends TLS, the app sees plain `http` unless it trusts the proxy's `X-Forwarded-*` headers, and every link above comes out `http://`. `bootstrap/app.php` does not trust any proxy yet, so add `$middleware->trustProxies(at: '*');` inside `withMiddleware` there. `'*'` suits a cloud balancer whose addresses are not fixed; where they are, list them instead.
- `PUBLIC_DISK_URL` is where uploaded cover photos are served from. Left at its default `/storage` it takes the scheme and host from the request, so it needs the proxy setting above. If the photos are served from a CDN or a bucket, set it to that origin's https address (`AWS_URL` for the `s3` disk).
- Check before anything is printed: open a trip page's source (or `curl -s https://tarana.example/t/<slug> | grep -E 'canonical|og:(url|image)'`) and confirm every address starts with `https://` and your own domain, then download that trip's QR code and scan it with a phone. A QR cannot be corrected once it is on paper; a preview Facebook has already fetched stays as it was until it is refreshed in Facebook's Sharing Debugger.

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
