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
cp .env.example .env                 # then set DB_PASSWORD to a long random value
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

## Certificates

- Each certificate has its own email reminders (for example 60 and 14 days before it expires). They go to the supplier's responsible buyer, or to the reminder email set by an admin under **Settings**.
- Admins can mark a certificate type as **required**. Active suppliers without a valid certificate of that type are flagged in the supplier list.
- Uploaded files (PDF, JPG, PNG, up to 10 MB) are stored privately and can only be downloaded by signed-in users.

## Everyday commands

```bash
./vendor/bin/sail artisan test            # run all tests
./vendor/bin/sail composer lint           # fix PHP code style
./vendor/bin/sail php vendor/bin/phpstan analyse   # find PHP type errors
./vendor/bin/sail pnpm run check:fix      # fix frontend style and lint
./vendor/bin/sail pnpm run types:check    # find TypeScript errors
```

Tip: add `alias sail='./vendor/bin/sail'` to your shell config to type less.

## Ground rules

- **Security first.** Every change is reviewed for security and performance before it is merged.
- **Readable code.** Code should explain itself through clear names and small functions. Comments are for _why_, not _what_.
- **Tests.** New behaviour comes with tests.
