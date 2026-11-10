# SRM – Supplier Relationship Management

A web app for procurement teams to manage supplier information. It works as a single-page app: pages change without full reloads.

## Tech stack

| Part                    | Tool                               |
| ----------------------- | ---------------------------------- |
| Backend                 | Laravel 13 (PHP 8.5)               |
| Frontend                | Vue 3 + TypeScript, via Inertia.js |
| Styling                 | Tailwind CSS                       |
| Database                | PostgreSQL 18                      |
| Cache, sessions, queues | Redis                              |
| Local setup             | Docker (Laravel Sail)              |

## Getting started

You only need **Docker** and **git**. Everything else runs inside containers.

```bash
cp .env.example .env                 # then set DB_PASSWORD to a long random value (Docker will not start without it)
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app composer:latest composer install
./vendor/bin/sail build              # build the app image (first time only)
./vendor/bin/sail up -d              # start the app, database, Redis, Mailpit and the queue worker
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan passport:keys   # keys that sign API tokens; kept out of git
./vendor/bin/sail artisan migrate
./vendor/bin/sail pnpm install
./vendor/bin/sail artisan app:create-user   # pick the Admin role; there is no public sign-up
./vendor/bin/sail pnpm run dev       # live-reloading frontend
```

Open http://localhost. Admins invite other people from the **Users** page.

Want something to click around in? Fill your local database with about 2,000 made-up suppliers (this refuses to run in production):

```bash
./vendor/bin/sail artisan db:seed --class=DemoSupplierSeeder
./vendor/bin/sail artisan db:seed --class=DemoCertificateSeeder
./vendor/bin/sail artisan db:seed --class=DemoContractSeeder
./vendor/bin/sail artisan db:seed --class=DemoRatingSeeder
```

Emails (invitations, password resets, certificate reminders) are sent in the background by the `queue` container and show up in Mailpit at http://localhost:8025.

The `scheduler` container runs timed jobs. Every morning at 06:00 (UTC) it emails certificate reminders: each person gets one email listing all their certificates that need renewing. To send due reminders right away while testing:

```bash
./vendor/bin/sail artisan app:send-certificate-reminders
```

After changing PHP code that runs in the background, restart the workers with `./vendor/bin/sail restart queue scheduler`.

## Roles

| Role   | Can do                                                                          |
| ------ | ------------------------------------------------------------------------------- |
| Admin  | Everything, plus users, categories, certificate types, API clients and settings |
| Buyer  | Add, edit and archive suppliers and their contacts                              |
| Viewer | Look only                                                                       |

Admins can't change their own role or deactivate themselves, so there is always at least one admin. Users are deactivated, never deleted, so their history stays intact.

Suppliers are archived, never deleted, and can be restored. Contacts are people, so they are truly deleted when removed.

## Ratings, risk and spend

- **Ratings:** buyers and admins score a supplier from 1 to 5 on each criterion. The overall score is the weighted average, and the latest rating (by date) counts. Admins manage the criteria and their weights, which cannot total more than 100%; changing a weight recalculates all saved overall scores and risk levels. A criterion old ratings use is retired, not deleted.
- **Risk level** is calculated live, and buyers and admins can override it with a reason:
    - **High:** the supplier is blocked, the latest rating is below 2.5, or a valid required certificate is missing.
    - **Medium:** the latest rating is below 3.5, or a certificate has expired or expires within 30 days with no replacement.
    - **Low:** everything else, including suppliers with no rating yet.
- **Contracts are left out of risk on purpose:** viewers can see risk, but not contract details.
- **Spend** is one amount per supplier per year, visible only to buyers and admins. Amounts in different currencies are not added together.

The thresholds are in `app/Enums/RiskLevel.php`. The rules exist twice and must change together: `app/Compliance/SupplierRisk.php` (the page) and `Supplier::withRiskLevel()` (the list filter). `SupplierRiskTest` fails if they disagree.

## Contracts and documents

- **Who sees what:** everyone can see how many contracts a supplier has. Only buyers and admins can see contract details, values and files.
- **Key date:** reminders count back from the last day to cancel for auto-renewing contracts, and from the end date for the rest.
- **Auto-renewal:** every morning at 05:30 (UTC), active auto-renewing contracts past their end date move forward by their renewal term. Set a contract to Terminated to stop this.
- **Reminders** go to the contract owner, then the responsible buyer, then the reminder email under **Settings**. Emails never include prices.
- **Documents** (PDF, JPG, PNG, .docx, .xlsx, up to 20 MB) are stored privately. Macro-enabled Office files are rejected.

To run these jobs right away while testing:

```bash
./vendor/bin/sail artisan app:renew-contracts
./vendor/bin/sail artisan app:send-contract-reminders
```

## Certificates

- Each certificate has its own email reminders (for example 60 and 14 days before it expires). They go to the supplier's responsible buyer, or to the reminder email set by an admin under **Settings**.
- Admins can mark a certificate type as **required**. Active suppliers without a valid certificate of that type are flagged in the supplier list.
- Uploaded files (PDF, JPG, PNG, up to 10 MB) are stored privately and can only be downloaded by signed-in users.

## Comments

- **Who writes:** buyers and admins can comment on a supplier and on its contracts. Everyone can read supplier comments; contract comments are for buyers and admins only, like the rest of the contract.
- **Where they show:** each page shows its own comments, oldest first, with the box for a new comment underneath. Supplier comments are on the supplier page and contract comments on the contract page.
- **Changing them:** people can edit their own comments (shown as "edited") and delete them. Admins can delete anyone's comment, for example to remove personal data.
- **Kept with their record:** comments on archived suppliers are read-only. Deleting a contract deletes its comments too.

## API for other systems

Other systems, such as an ERP or a reporting tool, can read SRM data through an API. There are no public endpoints: every request needs a valid access token.

- **Who gets access:** an admin adds the system on the **API clients** page, ticks what it may read and names a responsible person. The page asks for the admin's password again first.
- **Signing in:** the system uses OAuth 2.0 client credentials. It sends its client ID and secret to `POST /api/oauth/token` and gets an access token that lasts 15 minutes. There are no refresh tokens; it simply asks again. No other OAuth flows are offered.
- **The secret is shown once.** SRM keeps only a hash. If the secret is lost or may have leaked, create a new one: the old secret and its tokens stop working at once. Revoking a client is permanent; it stays in the list for the record.
- **What it can read** (scope `suppliers:read`): `GET /api/v1/suppliers` and `GET /api/v1/suppliers/{id}` return company details, categories, latest rating and risk level. Notes, contacts, the responsible buyer, contracts and spend are never included.
- **Paging and filters:** results come 100 at a time (`per_page`, up to 200). Pass the returned `next_cursor` as `cursor` for the next page. Filters: `status`, `updated_since` (changes to the supplier's own details) and `include_archived=true`.
- **Servers only:** web pages on other sites cannot call the API from a browser (CORS is off), and a login session never counts as API access.
- **Limits and records:** each client may make 120 requests a minute. An address that fails to get a token 10 times is blocked for 15 minutes. Every call is logged with the client, address and result in `storage/logs/api-*.log`, kept for 90 days.

```bash
curl -u "$CLIENT_ID:$CLIENT_SECRET" -d grant_type=client_credentials https://srm.example.com/api/oauth/token
curl -H "Authorization: Bearer $ACCESS_TOKEN" "https://srm.example.com/api/v1/suppliers?status=active"
```

On a server, create the signing keys once with `artisan passport:keys`, or put them in `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY`. Keep the same keys across deployments; new keys log every system out (they simply fetch a new token).

## Everyday commands

```bash
./vendor/bin/sail artisan test            # run all tests
./vendor/bin/sail composer lint           # fix PHP code style
./vendor/bin/sail php vendor/bin/phpstan analyse   # find PHP type errors
./vendor/bin/sail pnpm run check:fix      # fix frontend style and lint
./vendor/bin/sail pnpm run types:check    # find TypeScript errors
```

Tip: add `alias sail='./vendor/bin/sail'` to your shell config to type less.

## Browser tests

The tests in `tests/Browser` use a real Chrome browser to sign in as each role, click through the main features and check that viewers never receive contract or spend data. Every test also fails on a JavaScript error or a blocked script.

```bash
tests/Browser/run.sh                 # run all of them (about 1.5 minutes)
tests/Browser/run.sh viewer          # run only the files matching "viewer"
```

They run on a separate copy of the app that erases and refills the `testing` database, so your development data, files and emails are not touched. Don't run them at the same time as `sail artisan test`, which uses the same database. Stop `pnpm run dev` first.

If a test fails, the screenshot and a step-by-step trace are in `tests/Browser/.output`. On GitHub, they are attached to the failed run.

The Playwright version is set in three places that must match: `package.json`, `compose.yaml` and `.github/workflows/tests.yml`.

## Security rules for developers

- **No inline JavaScript.** The Content Security Policy only lets the browser run scripts from this app's own files. `<script>` blocks, `onclick="..."` attributes and scripts from other websites are blocked. If a Blade view truly needs an inline script, give it `nonce="{{ Vite::cspNonce() }}"`.
- **Never show user input as HTML.** Vue's `{{ }}` and Blade's `{{ }}` escape text automatically. Do not use `v-html` or `{!! !!}` with anything a user typed.
- **Check permissions on the server.** Hiding a button is only for convenience; every route needs a `->can(...)` check.

### Account protection

- **Links in emails come from `APP_URL`, never from the request.** Outside local development and tests the app only accepts requests for `APP_URL`'s own host name; set it to the real address.
- **Forgot password** gives the same answer whether or not the address has an account, and a wrong reset link gets the same error for every address. Both answers always take at least one second (`AUTH_TIMEBOX_DURATION`), so their speed doesn't give it away either. Asking for links and resetting passwords are limited to 5 tries a minute per IP address. Reset and invitation emails are sent in the background, encrypted while they wait in the queue because they hold working links.
- **Changing the email address** asks for the current password, and the old address gets an email about the change. Whoever controls the address can reset the password, so this keeps a few minutes at someone's unlocked computer from becoming a lasting takeover.
- **Changing or resetting the password** signs out every other browser where the person was signed in (Laravel's `AuthenticateSession`). API clients are not affected; they sign in with their own credentials.
- **Signing out** (and every other way of landing on the sign-in page: an expired session, a password change elsewhere, a deactivated account) clears the pages the browser kept, so the Back button can't show them to the next person. The browser keeps them encrypted (Inertia's history encryption), which needs HTTPS or `localhost`.

## Ground rules

- **Security first.** Every change is reviewed for security and performance before it is merged.
- **Readable code.** Code should explain itself through clear names and small functions. Comments are for _why_, not _what_.
- **Tests.** New behaviour comes with tests.
