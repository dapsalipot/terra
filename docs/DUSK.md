# Browser testing (Laravel Dusk)

TDD for Tarana uses **Dusk** for user-facing flows (real headless Chrome) and Pest for
pure logic (the reservation state machine, policies).

## One-time
- Chrome + matching driver: `php artisan dusk:chrome-driver --detect`
- Dedicated DB `terra_dusk` + `.env.dusk.local` (DB_DATABASE=terra_dusk, APP_URL=http://127.0.0.1:8030)

## Running
1. Start the Dusk app server on its own DB/port (keeps dev data intact):
   `DB_DATABASE=terra_dusk APP_URL=http://127.0.0.1:8030 php artisan serve --port=8030`
2. `php artisan dusk`

Dusk tests use `DatabaseTruncation` and create their own fixtures via factories.
Browser tests live in `tests/Browser/`; add `dusk="..."` attributes to elements the tests target.

## Contrast audit
`ContrastAuditTest` measures every piece of rendered text against the colour actually behind it, on the
public, joiner, host and admin pages at 1440 and 390px. When it fails it names the text, the ratio and its
classes. Fix the class rather than the test: muted text stays at `text-ink/65` or above, and words on an amber
or terra tint use `text-amber-deep` / `text-terra-deep`. Text over a photo or gradient is skipped, since there
is no flat colour to measure.
