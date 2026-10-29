# SRM – Supplier Relationship Management

A web app for procurement teams to manage supplier information. It works as a single-page app: pages change without full reloads.

## Tech stack

| Part        | Tool                                   |
|-------------|----------------------------------------|
| Backend     | Laravel 13 (PHP 8.5)                   |
| Frontend    | Vue 3 + TypeScript, via Inertia.js     |
| Styling     | Tailwind CSS                           |
| Database    | PostgreSQL 18                          |
| Cache, sessions, queues | Redis                      |
| Local setup | Docker (Laravel Sail)                  |

## Getting started

You only need **Docker** and **git**. Everything else runs inside containers.

```bash
cp .env.example .env                 # then set DB_PASSWORD to a long random value
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app composer:latest composer install
./vendor/bin/sail up -d              # start the containers
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail pnpm install
./vendor/bin/sail artisan app:create-user   # there is no public sign-up
./vendor/bin/sail pnpm run dev       # live-reloading frontend
```

Open http://localhost. Emails sent by the app (such as password resets) show up in Mailpit at http://localhost:8025.

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
- **Readable code.** Code should explain itself through clear names and small functions. Comments are for *why*, not *what*.
- **Tests.** New behaviour comes with tests.
