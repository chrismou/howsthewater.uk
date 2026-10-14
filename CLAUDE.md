<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>

# How's the Water

Live storm overflow spills and river levels for swimming, paddling and fishing spots on Yorkshire rivers. The site
reports what monitors say; it never claims water is safe or clean. Keep that distinction in all copy and labels.

## Architecture

- Controllers are thin: resolve the model, call a service, return a view or resource. All logic lives in
  `app/Services` (read side), `app/Actions` (write side, one `handle()` per class) and `app/Data` (readonly DTOs).
- Web pages and `/api/v1` share the same services and DTOs. `SpotConditionsService` is the single source of truth for a
  spot's status; `RiverOverviewService` wraps it for listings. Add new facts to the DTO first, then to both outputs.
- Data is ingested in the background by scheduled artisan commands (see `routes/console.php`) and served from MySQL.
  Pages never call third-party APIs at request time.
- Main rivers (Wharfe, Aire, Calder, Don, Nidd, Ure, Swale, Derwent, Ouse, Esk, Hull) and their tributaries are seeded
  by `RiverSeeder`, spots by `SpotSeeder`. Spot coordinates sit on the OS centreline; new ones should too.
- "Upstream" is resolved by `UpstreamMonitorFinder`: monitors on the same river with a smaller `river_metres` than
  the spot, plus everything on tributaries (rivers with a `parent_river_id`) that join at or above the spot,
  recursively. Distances follow the water. Positions come from projecting lat/lng onto the river centreline
  (`rivers:centrelines` then `rivers:positions`, which also sets each tributary's `joins_at_metres`).
- Tributaries have no pages. Use the `main()` scope wherever rivers are listed.
- `SpotConditionsService::for()` is cached per spot by `ConditionsCache` (versioned keys, 30 minute TTL). Ingest and
  positioning commands call `flush()`. The cache stores objects, so any new class reachable from `SpotConditions`
  must be added to `serializable_classes` in `config/cache.php` or reads fail with an incomplete class. Use
  `build()` in tests that change data between calls.
- Gauges carry a `unit` (`m` for stage above the gauge datum, `mAOD` for height above sea level). Spots prefer `m`
  gauges; show the unit via `GaugeStation::unitLabel()` wherever a level is printed.
- Spill history (`SpillHistoryService`) is derived from `spill_events`, which only exist from the first ingest run
  onwards. Don't backfill from the feed; it has no history.

## Data sources

- Storm overflows: Stream (Yorkshire Water) ArcGIS feature service, polled every 15 minutes by `ingest:overflows`.
  The feed only exposes each monitor's latest event, so `SyncOverflowMonitors` builds the spill history incrementally.
- River levels: Environment Agency flood monitoring API, `ingest:gauges` (add `--stations` to discover new gauges).
- River centrelines: OS Open Rivers GeoPackage at `OS_OPEN_RIVERS_PATH` (default `storage/app/private/oprvrs_gb.gpkg`,
  gitignored, download from the OS Data Hub). OpenStreetMap via Overpass is a fallback (`--source=osm`) but is unreliable.
  Names repeat (becks across Yorkshire, and Calder, Don, Derwent, Ouse and Esk across Britain), so `OpenRiversReader`
  seeds from the set of links whose mouth is nearest the parent centreline (tributaries) or the seeded `mouth_latitude`
  and `mouth_longitude` (main rivers), then joins other fragments within 4 km to bridge reservoirs. Seed parents before
  tributaries; `rivers:centrelines` orders them that way.
- The Environment Agency also names rivers nationally, so `SyncGaugeStations` drops stations more than 2 km from the
  centreline. Run `rivers:centrelines` before `ingest:gauges --stations` for a new river.
- No API keys are needed. Map tiles are OpenStreetMap.

## Local setup

```sh
php artisan migrate --seed
php artisan ingest:overflows
php artisan ingest:gauges --stations
php artisan rivers:centrelines
php artisan rivers:positions
```

## Frontend

- Blade + Tailwind 4 + Leaflet. Components live in `resources/views/components`; the layout is `components/layouts/app`.
- Build data for `data-*` attributes in a `@php` block and output with `{{ json_encode(...) }}`. Multi-line `@json(...)`
  inside attributes breaks the Blade compiler.
- Use a plain hyphen for dashes in any user-facing text.

## Quality

- Run `vendor/bin/pint --dirty --format agent`, `php artisan test --compact` and `vendor/bin/phpstan analyse` before
  finishing a change.

## Deployment

- `.github/workflows/tests.yml` runs `composer ci:check` (pint, phpstan, tests) on every push and PR. A push to `main`
  that passes then runs `dep deploy` from CI via `deployphp/action`, using the GitHub `production` environment
  (secrets `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS`; variables `DEPLOY_IP`, `DEPLOY_PORT`, `DEPLOY_USER`, `DEPLOY_PATH`).
- `deploy.php` uses the Laravel recipe plus an npm build. The server clones the repo through the deploy user's
  `github-howsthewater` SSH alias. Permissions follow the shared `www-data` group model: `writable_mode` is `chmod`,
  `writable_recursive` is off, the release is made read-only after the build, and file log channels set
  `'permission' => 0660` so web requests and deploy-time artisan can share a log file.
- Server provisioning (users, PHP-FPM, nginx, deploy keys, GitHub environment) lives in the `opendata-ansible` repo.
  Nothing is deployed by hand; never run `composer update` or merge on the server.
