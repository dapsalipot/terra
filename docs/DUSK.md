# Browser testing (Laravel Dusk)

TDD for Peeps uses **Dusk** for user-facing flows (real headless Chrome) and Pest for
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
