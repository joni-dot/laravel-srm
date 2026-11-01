# SRM – instructions for Claude

Supplier relationship management app for procurement teams. Laravel 13 (PHP 8.5), Inertia v3, Vue 3 + TypeScript, Tailwind 4, PostgreSQL 18 and Redis. It is a single-company app and a single-page app: no full page reloads.

[README.md](README.md) explains setup, roles and business rules. Read the relevant section before changing a feature. This repository is public, so never put secrets or real supplier data in it.

## Commands

The host has only Docker and git. Run PHP and Node commands through Sail.

```bash
./vendor/bin/sail up -d                  # start everything
./vendor/bin/sail composer ci:check      # all checks; must pass before a pull request
./vendor/bin/sail artisan test --filter=SupplierRiskTest   # one test class
./vendor/bin/sail composer lint          # fix PHP style
./vendor/bin/sail pnpm run check:fix     # fix frontend style and lint
./vendor/bin/sail restart queue scheduler  # after changing PHP that runs in the background
tests/Browser/run.sh                     # real-browser tests, run from the host; see /browser-test
```

`ci:check` runs the frontend checks, TypeScript, Pint, PHPStan and all tests. GitHub runs the same command and the browser tests on every pull request.

If a result makes no sense, Docker Desktop may have missed a quick file edit. Compare the file's sha256 on the host and in the container. If they differ, run `sail down` and then `sail up -d`.

## Git workflow

- Never commit to `main`. GitHub refuses direct pushes, and the `ci` check must pass before a merge.
- Use one branch per change, from an up-to-date `main`, named `<type>/<short-name>` (for example `feat/audit-log` or `fix/reminder-date`).
- Use Conventional Commits: `type(optional scope): short imperative summary`.
- Commit, push and open pull requests only when asked. The user merges pull requests.
- Before committing, check that no secrets are staged.

## Rules for every change

- **Security must be flawless.** Follow the security rules in the README.
- **Performance is a top priority.** Avoid repeated queries in lists, and check new filters against the demo data (about 2,000 suppliers).
- **Code explains itself** through clear names and small functions. Comments are for why, not what.
- **New behaviour comes with tests.** Add a browser test in `tests/Browser` when a feature adds a page or a user flow.
- **Report the result.** Every change summary includes a short security and performance check.
- **Explain in plain language.** Keep it brief, define unavoidable technical terms and give the reason for each decision. Ask when a choice is really the user's to make.

## Things that are easy to break

- **Viewers must not get restricted data.** Only buyers and admins see contract details (`ContractPolicy::viewDetails`) and spend (`SupplierSpendPolicy::viewAny`). Enforce this on the server by leaving the data out of the response, not by hiding it in Vue.
- **Some rules exist twice, in PHP for a page and in SQL for a list filter, and must change together.** Tests fail if they disagree:
    - Risk: `app/Compliance/SupplierRisk.php` and `Supplier::withRiskLevel()`
    - Required certificates: `app/Compliance/RequiredCertificates.php` and `Supplier::missingRequiredCertificates()`
    - Contract health: `Contract::health()` and `Contract::needingActionSoon()`
- **Dashboard tile counts must equal the supplier list each tile links to.** Archived suppliers never count.
- **Overall rating scores are calculated in one place only:** `app/Actions/Ratings/RecalculateOverallScores.php`.
- **Records are kept.** Suppliers are archived and users are deactivated, never deleted. Contacts are truly deleted for privacy.
- **Seeders must refuse to run in production.** `SeedersRefuseProductionTest` covers this, so add any new seeder to it.
- **Generated code:** `resources/js/actions`, `resources/js/routes` and `resources/js/wayfinder` are generated and ignored by git. Don't edit them.
