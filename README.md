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

| Role   | Can do                                                             |
| ------ | ------------------------------------------------------------------ |
| Admin  | Everything, plus users, categories, certificate types and settings |
| Buyer  | Add, edit and archive suppliers and their contacts                 |
| Viewer | Look only                                                          |

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

## Ground rules

- **Security first.** Every change is reviewed for security and performance before it is merged.
- **Readable code.** Code should explain itself through clear names and small functions. Comments are for _why_, not _what_.
- **Tests.** New behaviour comes with tests.
