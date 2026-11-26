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
Links that leave the site are absolute addresses this app builds: the `og:url` and `og:image` tags and the canonical link that previews and search engines read, the Copy and Facebook buttons in a share panel, the QR codes a host downloads for a trip or their page, and the sign-in and password reset links it emails. Outside the `local` and `testing` environments the app builds all of these from `APP_URL`, whatever address a request came in on (see `AppServiceProvider`). The one exception: a page with no address of its own, which is any page but a trip's or a host's, repeats the requested address in its canonical and `og:url` tags. Set this up before the first link goes out: Facebook and Messenger cache a preview, and a printed QR keeps its address for good.

- `APP_URL` is the public https address, for example `https://tarana.example`, with no trailing slash, and it must be exactly the address people use: if it is wrong, so is every emailed link. The `http://localhost` in `.env.example` is for development only.
- Redirect every other name for the site (`www.`, an old domain) to the `APP_URL` address at the web server. The app answers on any `Host`, but a visitor on another name is given links, upload addresses and redirects that point at `APP_URL`, where their sign-in does not follow them. Leave the health route, `/up`, answering directly: a load balancer's health check can arrive with the server's own address as `Host`.
- Behind a load balancer or reverse proxy that ends TLS, the app sees plain `http` unless it trusts the proxy's `X-Forwarded-*` headers, and file uploads then fail, because their signed `https` address no longer matches the request. It also takes the proxy's address for every visitor's, so each per-address limit counts the whole site as one visitor: once the site as a whole reached one address's daily booking limit, nobody else could book. `bootstrap/app.php` does not trust any proxy yet. Add this inside `withMiddleware` there, with `use Illuminate\Http\Request;` at the top of the file:

  ```php
  $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_AWS_ELB);
  ```

  Keep the `headers:` argument. Despite its name, that value suits any such proxy: it trusts the forwarded client address, scheme and port, but not `X-Forwarded-Host`. Without the argument Laravel takes the host from that header too, so whoever can get it through your proxy decides what the app takes its own address to be. Emailed links no longer follow it, but signature checks and the tags above still would. If your setup does need the forwarded host, trust it only when the proxy overwrites that header on every request.

  `'*'` treats whatever connects as the proxy, so use it only when nothing but your proxy can reach the app. Otherwise a direct caller can fake their IP address and get past the rate limits on password sign-in, sign-in links, the join page and booking. `'*'` suits a cloud balancer whose addresses are not fixed; where they are, list them instead.
- `PUBLIC_DISK_URL` is where uploaded cover photos are served from. Left at its default `/storage`, pages use it as it is and previews put `APP_URL` in front of it. If a CDN serves the photos, set it to the CDN's https address. Covers always come from the `public` disk, so if you move that disk to a bucket (see Storage), its own entry in `config/filesystems.php` needs a `url` with the bucket's or CDN's https address. `AWS_URL` alone changes no cover address: it only configures the separate `s3` disk.
- Check before anything is printed: open a trip page's source (or `curl -s https://tarana.example/t/<slug> | grep -E 'canonical|og:(url|image)'`) and confirm every address starts with `https://` and your own domain, then download that trip's QR code and scan it with a phone. Use "Forgot your password?" with your own account too, and check that the link in that email also starts with `https://` and your own domain. A QR cannot be corrected once it is on paper; a preview Facebook has already fetched stays as it was until it is refreshed in Facebook's Sharing Debugger.

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
