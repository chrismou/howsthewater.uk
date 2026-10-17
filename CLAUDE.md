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

Live storm overflow spills and river levels for swimming, paddling and fishing spots, by region (Yorkshire, Thames Valley, North West, North East, Midlands, Scotland, Wales). The site
reports what monitors say; it never claims water is safe or clean. Keep that distinction in all copy and labels.

## Architecture

- Controllers are thin: resolve the model, call a service, return a view or resource. All logic lives in
  `app/Services` (read side), `app/Actions` (write side, one `handle()` per class) and `app/Data` (readonly DTOs).
- Web pages and `/api/v1` share the same services and DTOs. `SpotConditionsService` is the single source of truth for a
  spot's status; `RiverOverviewService` wraps it for listings. Add new facts to the DTO first, then to both outputs.
- Data is ingested in the background by scheduled artisan commands (see `routes/console.php`) and served from MySQL.
  Pages never call third-party APIs at request time.
- Rivers are seeded per region: Yorkshire's main rivers (Wharfe, Aire, Calder, Don, Nidd, Ure, Swale, Derwent, Ouse,
  Esk, Hull) and the Thames (source to Teddington, mouth hint in central London because the tidal Thames is split into
  pieces too far apart to join), the North West's Ribble, Lune, Kent, Eden and Cumbrian Derwent (United
  Utilities, mouths at the head of each estuary), and the North East's Tyne (with the North and South Tyne flowing
  into it), Wear, Tees, Coquet, Aln, Wansbeck, Blyth and Tweed, with the Till flowing into the Tweed (Northumbrian
  Water, plus Scottish Water for the Tweed, which runs mostly through Scotland; its Scottish stretch has overflows and
  SEPA levels and rainfall but no flood warnings yet). The Midlands has Severn
  Trent's rivers: the Trent, with the Soar and the Peak District's Derwent, Wye, Dove and Manifold flowing into it, and
  the Severn, with the Teme and Warwickshire Avon. Overflows on the Welsh upper Severn (Hafren Dyfrdwy) are not on
  Stream, so they do not count yet. Scotland has Scottish Water's rivers, grouped by catchment: the Tay (with the
  Tummel, Garry, Braan and Earn flowing into it), Spey, Ness, Findhorn, Lossie, Dee, Don, Forth (with the Teith, Allan
  Water and Devon), the Lothian rivers (Water of Leith, Almond, Esk, Tyne), the Clyde (with the Kelvin and White
  Cart), the Dunbartonshire Leven, the Ayrshire rivers (Irvine, Ayr, Doon), the Fife Eden, the Angus North and South
  Esk, and the Nith, Annan and Galloway Dee (slug `dee-galloway`). Many share names with English
  or other Scottish rivers, so each main river's mouth hint matters. Scotland has SEPA levels, rainfall and bathing
  waters (with samples while switched on) but no flood warnings yet. Wales has the Taff and Usk, the Wye (with the
  Irfon, Ithon, Lugg and Monnow, running on into England to Monmouth), the Tawe, Neath (with the Mellte), Towy, Teifi
  and Western Cleddau, the Dovey, Mawddach (with the Wnion), Glaslyn, Conwy (with the Llugwy) and the Dee (with the
  Tryweryn, running on to Chester). Welsh rivers take their OS name: the English alternative where there is one
  (River Usk), the Welsh name where that is the only one (Afon Teifi), with the other form as an alias; slugs drop
  "River" or "Afon". Wales has bathing waters (Wales's register, `Agency::NaturalResourcesWales`) and NRW's levels,
  rainfall and flood warnings, but no overflows yet: Dŵr Cymru Welsh Water's storm overflow map is recorded
  (`welsh_water`) but Wales's `overflow_feeds` stays empty, so nothing shows, until it is switched on.
  Main rivers and their tributaries are seeded
  by `RiverSeeder` (regions and their overflow feeds come from `RegionSeeder`), spots by `SpotSeeder`, whose `FEATURED` list
  marks the spots suggested on the home page. Spot coordinates sit on the OS centreline; new ones should too.
- "Upstream" is resolved by `UpstreamMonitorFinder`: monitors on the same river with a smaller `river_metres` than
  the spot, plus everything on tributaries (rivers with a `parent_river_id`) and on main rivers that flow into it
  (`flows_into_river_id`, set by `flowsInto:` in `RiverSeeder`, e.g. the Ure into the Ouse) that join at or above the
  spot, recursively. A main river that flows into another keeps its own pages; river and region monitor counts still
  stop at main rivers, so nothing is counted twice. Spot pages render the first 10 rows of the overflows table
  (recent spills first, via `SpotConditions::upstreamMonitorsByActivity()`) and load the rest from `spots.monitors`,
  since spots low on a big system (Goole) have hundreds upstream. Distances follow the water.
  Positions come from projecting lat/lng onto the river centreline (`rivers:centrelines` then `rivers:positions`,
  which also sets each tributary's and inflowing main river's `joins_at_metres`).
- Beaches and lakes come from the EA designated bathing water register (Wales's register sits on the same platform
  under `/wales/bathing-waters`, read by `BathingWaterClient::wales()` for NRW sites, which name sites by `label`),
  and in Scotland from SEPA's open map service
  (`Sepa\BathingWaterClient`, `SyncBathingWaters::handleSepa()`): location, classification and a link to SEPA's
  results page (`profile_url`). SEPA publishes samples only on those HTML pages, so `ingest:sepa-bathing-samples`
  (`SyncSepaBathingWaterSamples`, `Sepa\BathingWaterResultsClient`) reads them one site at a time, only while
  `SEPA_BATHING_SAMPLES` is on and from 15 May to 30 September: every site once per
  `SEPA_BATHING_SAMPLES_INTERVAL_DAYS` (default 7, never under 1), at an even pace (90 sites over 7 days is one request
  every 112 minutes), longest unread first. It runs every minute and exits until the next turn is due. A 403 or 429
  pauses all reads for a day, any other failure for an hour, and the failing site waits a day (`samples_retry_at`).
  Timing reads to each site's expected next result was simulated and did no better than even rotation, as SEPA's
  sampling dates are too irregular (extra rounds two days apart, gaps of 8 to 13 days). There are no Scottish
  forecasts. The reader
  gives each Scottish site its council area (the Scotland region's `bathing_water_districts`) and names the loch for
  the three inland sites (`lakeName`, since Luss Bay does not start with Loch Lomond). Bathing waters carry an
  `agency`; the EA history backfill skips SEPA sites. `BathingWater` holds every site we track:
  beaches (coastal and estuary) belong to the region whose `bathing_water_districts` lists their district, lake sites
  to a seeded `Lake` (`LakeSeeder`, matched by name prefix), and river sites to a spot within 1.5 km, which then shows
  them. `WaterBodyConditionsService` builds `BeachConditions` and `LakeConditions` (cached through
  `ConditionsCache::remember()`): the bathing water record plus `NearbyOverflows`, every monitor within 3 km by
  straight-line distance, since the sea and lakes have no upstream. Use `SpillRecency::nearbyLabel()` there, never the
  "upstream" wording. A region's rivers, beaches and lakes each have a listing (`/{region}/rivers`, `/beaches`,
  `/lakes`), and `/{region}` is an overview with a `components/region-card` (`inRegion`) for each kind of place it
  has, plus its headline figures and flood warnings. The Rivers, Beaches and Lakes menus all render through `components/nav-menu` as plain links to `/rivers`,
  `/beaches` and `/lakes` (`PlaceHubController`), which list every region with that kind of place as cards
  (`components/region-card`, also used on the home page), so the menus work without JavaScript. The regions and places
  come from `NavigationMenus::menusJson()`, embedded once per page as `#nav-menus`, and `nav.js` builds each menu from it
  when it first opens, so the header stays small and the content comes early in the HTML. The hub routes, `/about`,
  `/privacy`, `/terms` and `/search` sit before `/{region}`, so `Region::RESERVED_SLUGS` lists them and saving a region
  with one throws. `RegionSummary` carries each region's beaches and lakes as plain `WaterBodySummary` values for the
  Beaches and Lakes hubs.
- RSS feeds for each spot, river, beach and lake (`spots.feed`, `rivers.feed`, `beaches.feed`, `lakes.feed`, at
  `.../feed`) come from `ConditionsFeed`: one item when an upstream (or, for beaches and lakes, nearby) overflow starts,
  one when it stops, one per flood warning in force, and for bathing waters one per sample and one per increased
  pollution risk forecast in force, over 30 days, capped at 50.
  Guids are stable per event so readers notify once. The feed and spot monitors routes sit before the spot route,
  which would otherwise take "feed" or "monitors" as a spot slug. Spot, river, beach and lake pages pass `feed` to the layout for the `<link rel="alternate">`.
- `SpillRecency::Offline` is returned when every upstream monitor is offline, so an absence of data never shows as a
  green "no spills". Keep that distinction for any new status. A region without overflow feeds
  (`Region::hasOverflowData()`, Wales for now) gives `SpillRecency::NotCovered`: pages say "Overflow data not available
  here yet" and show dashes, never zero counts, "no monitored overflows" or a 30-day history, and `RegionSummary`
  carries `hasOverflowData` for the live bar, region page and cards. Where `services.unread_overflow_maps` names the
  region's company and map (Dŵr Cymru Welsh Water), those messages also link it (`Region::unreadOverflowMap()`,
  `components/overflow-map-link`).
- Tributaries have no pages. Use the `main()` scope wherever rivers are listed, and `Spot::onMainRiver()` wherever spots
  are listed; the spot controllers 404 a spot on a tributary.
- Rivers belong to a `Region` and URLs are region-scoped (`/{region}/rivers/{river}/{spot}`, API `/api/v1/regions/...`) with
  `scopeBindings()`, because river and spot names repeat across Britain; slugs are unique per region and per river.
  Rivers and spots used to sit straight under the region (`/{region}/{river}/{spot}`, and their feeds), so those
  addresses 301 to the new ones (`LegacyRiverRedirectController`, registered last so every other route wins).
  Build links with `route('spots.show', [$region, $river, $spot])` and eager-load `river.region` wherever spots are
  listed. The header's Rivers, Beaches and Lakes menus come from `NavigationMenus`: plain arrays built once
  (rivers grouped by `catchment` via `Region::riversByCatchment()`, beaches by district), cached and rebuilt in
  place by `regions:refresh`, so pages never query per region for the menus. The search box queries `/search`.
  Search, feeds and the API are rate limited per IP by the `public` limiter in `AppServiceProvider`.
- Listings read `RegionSummary` (plain-value `RiverSummary` and `SpotSummary` objects) from `RegionSummaries`. Ingest and
  positioning commands never rebuild caches themselves: they mark the regions they touched with `ChangedRegions`, and
  `regions:refresh` (scheduled last, every 15 minutes, `--all` on deploy, which never runs `cache:clear`) rewrites those regions' spot, beach and lake
  conditions, summaries and the menus in place, so visitors never hit a cold cache. The region page, live bar and API region endpoint never
  unserialise full conditions. Add listing facts to `SpotSummary::fromConditions()`.
- `SpotConditionsService::for()` is cached per spot by `ConditionsCache` (versioned keys, 30 minute TTL), warmed by
  `regions:refresh`; `flush()` remains for clearing everything. The cache stores objects, so any new class reachable from `SpotConditions`
  must be added to `serializable_classes` in `config/cache.php` or reads fail with an incomplete class. Keys also
  carry a hash of those classes' source, so a deploy that changes their shape never reads the old release's entries.
  Use `build()` in tests that change data between calls. Rivers inside cached conditions are loaded with
  `withoutCentreline()` (or a slim clone of the bound river), so pages take the centreline for their map from the
  route-bound river, never from `$conditions->spot->river`.
- Gauges carry a `unit` (`m` for stage above the gauge datum, `mAOD` for height above sea level). Spots link the nearest gauge within 25 km
  along the river (none beyond that, rather than a reading far away) and prefer `m` gauges; show the unit via `GaugeStation::unitLabel()` wherever a level is printed.
- Stale data: `SpotConditions::overflowDataIsStale()` and `LevelSummary::isStale()` compare the feed timestamp or latest
  reading against `STALE_AFTER_HOURS` (6) at read time, so cached conditions still go stale on schedule. Show the
  warning and the neutral badge wherever a spill status or level is printed: the badge then reads "Overflow data out
  of date", and each overflows table row (`components/overflow-status`, `OverflowMonitor::isStale()`) says when the
  monitor last reported instead of "Not discharging". Beach and lake summaries carry the same check for the Nearby
  section. The API exposes `data_stale` and `level.stale`.
- Spill history (`SpillHistoryService`) is derived from `spill_events`, which only exist from the first ingest run
  onwards. Don't backfill from the feed; it has no history.

## Data sources

- Storm overflows: one feed per water company, listed in `services.overflow_feeds`, each with a `driver`. Stream
  publishes an ArcGIS feature service per English company, all with the same fields (`stream`, `StormOverflowClient`);
  Scottish Water publishes its own hourly API (`scottish_water`, `ScottishWater\OverflowEventClient`), and Dŵr Cymru
  Welsh Water its own ArcGIS map (`welsh_water`, `WelshWater\StormOverflowMapClient`: statuses in words, UK local
  times without an offset, the position only in the geometry, ids already prefixed `DCWW`). Each driver
  implements `OverflowFeedReader` and maps its records into `OverflowMonitorData`, so the sync and everything after it
  never see the source format; a new company with other fields needs only a new reader. `ingest:overflows` polls every
  configured feed every 15 minutes, whether or not a region names it in `overflow_feeds`, because spill history that is
  not recorded as it happens cannot be filled in later (Anglian, Wessex, Southern, South West and Welsh Water are
  recorded ahead of their regions). A region's `overflow_feeds` still decides `hasOverflowData()` and its company names; the footer and
  `/about` credit every polled feed. A failing feed is logged and skipped so the others still sync. Monitor IDs are prefixed by company (`YWS`, `NWL`, `UUP`, `SW`...) so feeds
  share one table. The footer credits Stream feeds, Scottish Water and Welsh Water separately. South West Water's
  Stream fields are camelCase, so `OverflowMonitorData::fromArcGis()` matches names case-insensitively. Some services
  return fewer records per page than asked for (Anglian and Southern Water stop at 1,000), so paging moves on by what
  came back. Hafren Dyfrdwy publishes no live feed.
  Most Stream companies refresh every monitor's `LastUpdated` on each publish, but Northumbrian Water only moves it
  when a monitor's status changes, so its feed is marked `updated_on_change`: while anything in the feed changed in the
  last 48 hours (`SyncOverflowMonitors::QUIET_FEED_HOURS`), each read sets its monitors' `source_updated_at` to the read
  time; past that they keep their own times and go stale. Southern Water and Welsh Water behave the same way. Check a new company's
  feed for the same behaviour.
  The feed only exposes each monitor's latest event, so `SyncOverflowMonitors` builds the spill history incrementally.
- River levels: Environment Agency flood monitoring API, `ingest:gauges` (add `--stations` to discover new gauges).
  Readings follow the API's own advice rather than one request per gauge: `FloodMonitoringClient::readingsSinceInBulk()`
  reads every measure's latest reading in one `data/readings?latest` call (cached 5 minutes, so the level and rainfall
  ingests share it), and only a gauge with a gap (over an hour for levels, any missed 15 minutes for rainfall, since
  many rain gauges report hourly in batches) or no readings gets its own `since` query: at most 20 level and 50 rain
  gauges per run, longest gap first. Crawling every gauge every run (about 870 requests) got the server briefly
  refused with 403s.
- Scottish levels and rainfall: SEPA's time series API (KiWIS, `Sepa\TimeSeriesClient`, OGL, no key). Gauges and rain
  gauges carry a `network` (`Agency`), external IDs are prefixed `SEPA`, and `measure_id` is the KiWIS series id.
  Anonymous use is capped at 5,000 credits a day (1 per list request, 1 per 1,000 values), so the national station
  lists are read once per run, SEPA gauges are matched to a river by its SEPA name ("Tweed", "Ettrick Water") within
  2 km of the centreline (skipping border gauges the Environment Agency also publishes, such as Norham), and
  readings for up to date gauges share one request. The typical range is SEPA's normal
  range, the median annual minimum to median annual maximum. A SEPA failure is logged and the Environment Agency
  gauges still sync.
- Welsh levels, rainfall and flood warnings: Natural Resources Wales's API (`NaturalResourcesWales\Api`), which needs
  a free key per product (`NRW_LEVELS_API_KEY`, `NRW_FLOOD_WARNINGS_API_KEY`; `NRW_FLOOD_RISK_API_KEY` for the 5-day
  flood risk, not used yet). Each product allows 10 calls a minute and 2,880 a day, so `Api` counts calls per product
  in the cache (`RateLimiter`, a little under both) and refuses beyond that; NRW data is skipped while a key is blank,
  as it is in tests. `RiversAndSeasClient` reads every station's latest 15 minute reading in one call, cached for
  5 minutes so the level and rainfall ingests share it. Its history call returns a whole year for one parameter (and
  returns whatever parameter it is given, even another gauge's, so always pass the gauge's own `measure_id`); it only
  fills new gauges and gaps (over an hour for levels, any missed reading for rainfall), at most 3 per ingest per run,
  longest gap first, so new gauges fill in over a few hours. Gauges are matched by the river in their title ("Wye at
  Glasbury", `River::matchesBareName()`) within 2 km of the centreline, external IDs are `NRW` plus NRW's location
  and `measure_id` is the parameter id. NRW publishes no typical range, so each history read sets it to the 5th to
  95th percentile level over at least 180 days. Flood warnings come from `/floodwarnings/v3/all` and the alert and
  warning area outlines from DataMapWales's open WFS (`FloodWarningsClient::floodAreasNear()`, searched for spots
  inside NRW's extent); some NRW areas name the river without "River" or leave it blank, so those match on the
  area's name or description. `flood_areas.agency` keeps each agency's warnings and history apart: each feed only
  replaces and closes its own. NRW asks that its data is not kept over a year, so `nrw:prune` (daily) deletes NRW
  readings and Welsh flood warning events over 365 days old.
- Rainfall: the Environment Agency API's tipping bucket rain gauges (and SEPA's), `ingest:rainfall`. Each spot, beach and lake links to the
  nearest gauge within 20 km (`LinkNearestRainfallStations`, also run by `rivers:positions`); gauge discovery searches
  around all three per region, readings are fetched for any gauge `inUse()`, and totals are sums of 15 minute readings.
  Spots, beaches and lakes each record `rain_gauges_searched_at`, so new ones are picked up by the next run.
- Flood alerts and warnings: the same API's `/id/floods`, `ingest:floods`. `SyncFloodAreas` links each spot to the flood
  areas whose outline comes within 1 km and whose `riverOrSea` names the spot's river (`River::matchesWatercourse()`).
  The whole national feed is read every 15 minutes and `flood_warnings` is replaced with what is in force for linked
  areas (severity 1-3; level 4 "no longer in force" is dropped). The same read feeds `RecordFloodWarningHistory`, which
  keeps `flood_warning_events` for every area in England (creating `flood_areas` rows for areas no spot uses): one row
  per area and severity, closed when the EA lifts it, changes its severity or it drops out of the feed. It is the only
  flood history; the feed keeps none, so it cannot be backfilled. EA flood times carry no offset and are UK local time.
  `FloodWarningSummary::isStale()` flags a warning no poll has confirmed for 6 hours. The area search is slow (a few
  seconds per spot), so it only runs for spots with a null `flood_areas_searched_at`, or all spots with `--areas`.
- River centrelines: OS Open Rivers GeoPackage at `OS_OPEN_RIVERS_PATH` (default `storage/app/private/oprvrs_gb.gpkg`,
  gitignored, download from the OS Data Hub). OpenStreetMap via Overpass is a fallback (`--source=osm`) but is unreliable.
  Names repeat (becks across Yorkshire; Calder, Don, Derwent, Ouse and Esk across Britain; two River Gretas in the North
  West, the Keswick one seeded as `greta-keswick`), so `OpenRiversReader`
  seeds from the set of links whose mouth is nearest the parent centreline (tributaries) or the seeded `mouth_latitude`
  and `mouth_longitude` (main rivers), then joins other fragments within 4 km to bridge reservoirs, always the nearest
  first (OS splits the Tweed into dozens of pieces). Within each set of
  connected links the main stem runs from the flow sink nearest the parent or hint to the node furthest from it along
  the links, ignoring recorded flow directions, which OS often draws backwards in braided reaches. Rivers that rise
  in Wales carry their Welsh name first (`Afon Hafren`, alternative `River Severn`), so links are matched on
  `watercourse_name` or `watercourse_name_alternative`. Seed parents before
  tributaries; `rivers:centrelines` orders them that way. It only fetches rivers without a centreline, so a fix that
  should reach an existing river needs a migration that clears its `centreline` (deploys then refetch it).
- The Environment Agency also names rivers nationally, so `SyncGaugeStations` drops stations more than 2 km from the
  centreline. Rivers are only searched for gauges once they have a centreline.
- New rivers, spots and monitors need no manual commands. Rivers record `gauges_discovered_at` and spots
  `rain_gauges_searched_at` and `flood_areas_searched_at`; the 15-minute `ingest:gauges`, `ingest:rainfall` and
  `ingest:floods` search anything still null, and `ingest:overflows` positions monitors it sees for the first time.
  `--stations` (and `--areas` for floods) re-checks everything (weekly).
- Weather: Open-Meteo's forecast API (`Weather\OpenMeteoClient`, CC BY 4.0, no key), `ingest:weather` every three
  hours at seven minutes past. It is free for non-commercial use only: once the site makes money through
  subscriptions or ads, it needs Open-Meteo's paid plan. Places share forecasts by grid cell (`WeatherCell`, about
  5.5 km across, about 600 cells for about 720 places), read 25 to a request with a pause between, and the client
  counts calls in the cache to stay under Open-Meteo's per-minute, hourly and daily allowances. A cell read in the
  last two and a half hours is skipped, and a 5xx or timeout gets two more tries (Open-Meteo returns the odd 503), and any other failure ends the run, keeping each cell's last forecast. Each
  cell stores 24 hourly values, so pages show whichever hour is current when viewed (`WeatherSummary`); sunrise,
  sunset and first and last light (`SunTimes`) are worked out from each place's position. Weather is not part of
  the cached conditions or the API yet. Tide times wait on a UKHO licence.
- No API keys are needed apart from NRW's. Every request sends a user agent with `CONTACT_EMAIL` (`app.contact_email`), so providers can reach us. Map tiles are OpenStreetMap.
- Bathing waters: the EA register at `environment.data.gov.uk/doc/bathing-water.json`, `ingest:bathing-waters` (hourly).
  One request returns every site's latest classification, sample and pollution risk forecast; each run stores the
  latest sample, and sample and classification history is backfilled per site on first sight (and weekly with
  `--history`). The same command links each lake to its EA level gauge by `gauge_reference`; the gauge readings sync
  keeps it current. Forecasts only run 15 May to 30 Sep; `BathingWaterSummary::currentRisk()` returns null outside the
  season or once the forecast expires. Copy reports classifications, counts and forecasts, never that water is safe.
- Ingest failures are logged at `error` level, which the `rollbar` log channel forwards in production (it ignores
  anything below `error`). A failing feed, river, flood area search or bathing water request logs its own error, and
  Laravel's scheduler itself reports any scheduled command that exits non-zero, so don't add `onFailure()` hooks for
  that (they duplicate it). Keep new ingest failures at `error` or they will not be seen.

## Local setup

```sh
php artisan migrate --seed
php artisan ingest:overflows
php artisan ingest:gauges --stations
php artisan ingest:rainfall --stations
php artisan ingest:floods
php artisan ingest:bathing-waters
php artisan rivers:centrelines
php artisan rivers:positions
php artisan regions:refresh --all
```

When adding a region locally, pass `--quick` to `ingest:floods` and `ingest:bathing-waters`: it skips the slow flood
area search and bathing water history backfill, which the scheduled runs on live fill in. Refresh local data by
replacing the local database with a copy of live rather than backfilling.

## Frontend

- Blade + Tailwind 4 + Leaflet. Components live in `resources/views/components`; the layout is `components/layouts/app`.
- Fonts (Instrument Sans, Bricolage Grotesque, IBM Plex Mono) are self-hosted by the Laravel Vite font plugin
  (`vite.config.js`, downloaded from Google Fonts at build time as one woff2 each; not Bunny, whose woff
  files this plugin version emits as separate rules that override the woff2) and loaded by `@fonts` in the layout, with font-display: swap
  and preloads only for the faces on screen first. No font request leaves the site. With `fontaine` installed the
  plugin also writes a metric-matched "<name> fallback" face for each (local Arial with `size-adjust`), which the
  `--font-*` stacks in `app.css` name second so text barely moves when the web font arrives.
- Build data for `data-*` attributes in a `@php` block and output with `{{ json_encode(...) }}`. Multi-line `@json(...)`
  inside attributes breaks the Blade compiler.
- Use a plain hyphen for dashes in any user-facing text.
- Colours are semantic tokens defined in `resources/css/app.css` (`bg-ground`, `bg-surface`, `text-ink`, `text-muted`,
  `border-line`, `text-accent`, `bg-pill-*`, `bg-warn-*`), each with a dark value under `.dark`. Never use raw
  Tailwind palette classes in views. Dark mode is a class on `<html>` set by the header toggle and remembered in
  `localStorage`; light is the default. Headings use `font-display`, big readings use `font-mono tabular-nums`.
- Explain data terms with `<x-help term="..." />`, whose glossary lives in `App\View\Components\Help`. Add a term there
  rather than writing one-off explanations in views. The component renders only the `?` button, placed beside a heading
  (heading and button in a flex row), never inside it;
  each term's text goes once into a hidden glossary at the end of the page, and `tooltip.js` shows it in one shared
  panel kept on screen, so headings never contain the explanations.
- Phones keep the header to two rows: the search box sits behind a search button (`search.js`; without JavaScript it
  links to `/search`, which has its own box on phones). Controls people tap (menus, buttons, the theme toggle) are at
  least 44px tall on phones; small inline ones (help buttons, breadcrumb links) reach further than they are drawn.
- Spot, beach and lake pages show the weather and daylight as one full-width band under the status card
  (`components/weather-card`, from `PlaceWeather`), with a line icon per hour (`components/weather-icon`, one per
  group of WMO codes from `WeatherCode::icon()`, the moon instead of the sun after sunset) and end with general advice for getting in the water (`components/before-you-go`, by kind of
  place), which says what to check and never whether a place is safe.
- Spot, beach and lake pages share `components/place-actions`: share (copy link on desktops; `share.js` switches to the
  share sheet on touch screens that have one), RSS feed, and open in OpenStreetMap and Google Maps, as a two by two grid
  on phones.

## SEO

- Every page title ends with " - How's the Water?", which the layout appends to the `:title` a page passes. The 60
  character limit applies to that unique part, not the site name: search results show about 60 characters, so if they
  cut a title it is the site name that goes, never the place or topic. `og:title` and `twitter:title` leave the site
  name off, since share previews show `og:site_name` or the domain beside them. Meta descriptions stay within 160
  characters and lead with the place's own facts, since share previews cut them sooner (often around 125). The
  layout prints titles, descriptions and image alt text in the head through `$meta`, which escapes `&`, `<`, `>` and
  `"` but leaves apostrophes as typed: Google's AI Overview source cards show an escaped one as "&#039;".
- Spot H1s name the river ("Ilkley, River Wharfe"), and spot descriptions carry the spot's own facts (distance from
  the source, overflows upstream, gauge) so no two read alike. River titles lead with the river and "levels". Titles
  on rivers and spots whose overflow data we do not read never mention spills.
- Never list every region (or every company, river or place) in a site-wide title or description: they grow with each
  region added. Name the countries ("England, Scotland and Wales") or describe the coverage instead; the home page hero lists the regions.
- When adding a region, page type or new place names, check the longest titles and descriptions it produces.
  `PageMetadataTest` checks the home, search, about and legal pages with many long region names, and place pages with
  long place names; extend it for any new page type.
- Spot, beach and lake pages open with a headline (`PlaceHeadline`, `components/place-summary`): the overflow state
  in plain words ("No spills reported upstream in the last 48 hours", "Last upstream spill ended 6 hours ago",
  "Overflow data is out of date"), the level, a flood warning or bathing water result, with those facts in bold;
  then notes only when a threshold is crossed (10 mm of rain in the last 24 hours; heavy rain, thunder or, on beaches
  and lakes, gusts of 35 mph forecast in the next 12 hours; wind never on rivers, as it does not speed them up); then
  a small source line with the monitors, company and time, which keeps the page's dated, attributed statement. River
  pages keep one dated sentence (`components/dated-summary`) with counts for the river and its tributaries. Places
  with a designated bathing water (beaches, spots with a river site, lakes) lead with its classification; a lake with
  several sites counts them by classification. With half or more monitors offline, a quiet headline leads with how
  few are reporting, never with "No spills reported". A forecast note adds "Forecast from Open-Meteo, read ..." to the
  source line.
  Exclamation marks stay rare: only the thunder note has one.
- `public/robots.txt` welcomes search engines and AI crawlers that search or fetch pages to answer questions (and
  cite them), and asks training-only crawlers (GPTBot, ClaudeBot, Applebot-Extended, CCBot and others) not to crawl.
  Google-Extended is not in that group: it also controls whether Gemini and Vertex AI ground answers in the pages.
  Add new training-only crawlers to that group, never to `*`.
- Levels are described in everyday words on pages: "at its usual level", "higher than usual", "lower than usual"
  (`LevelSummary::usualPhrase()`), never "typical range". The API keeps its `within typical range` values. `/about` explains the sources and methods; keep it in step with Data sources below.
- `/about`, `/privacy` and `/terms` show a "Last updated" date from `app.pages_updated`, which also goes into their
  `dateModified` and sitemap `lastmod`. Bump a page's date whenever its content changes. Other pages' `lastmod` and
  `dateModified` are the newest of their overflow, level and rain data (`SpotSummary::latestDataAt()`), so places
  without overflow data still carry one.
- Pages describe themselves as schema.org JSON-LD through the layout's `structuredData` (built by
  `App\Services\StructuredData`) beside the `BreadcrumbList`: `WebSite` and `Organization` on the home page, a `Place`
  (on its `RiverBodyOfWater`) per spot, `Beach` and `LakeBodyOfWater` with coordinates, and an `ItemList` on listing
  pages. State only what the page does; never add ratings, reviews or anything implying the water is safe. Build JSON-LD
  arrays in `@php`, not inside `{!! !!}`, where Blade reads the "@context" key as a directive.
- Breadcrumb JSON-LD follows the URL (Home, region, Rivers, Beaches or Lakes, river, place). The visible trail's first link goes to
  the matching hub (All rivers, All beaches, All lakes) rather than the home page.
- Spot, river, beach and lake pages share their own card (`og:image`, 1200 by 630): `share-images:generate`
  (`GenerateShareImages`, run on every deploy) draws it with GD (`ShareCardRenderer`, TTF copies of the site fonts in
  `resources/fonts`) from what `ShareCards` says: the place, where it is and what the page covers, with the river's
  line and its spots, or Britain with a pin (`resources/geo`, Natural Earth). Never live status: social sites keep a
  card for days. A card is only redrawn when its fingerprint changes, and the file name carries the fingerprint, so
  bump `ShareCardRenderer::VERSION` whenever the drawing changes. Cards live on the public disk under `share/`, rows in
  `share_images`; pages fall back to `og-image.png` until theirs is drawn.
- Spot, beach and lake pages end with a Nearby section (`NearbyPlaceFinder`, `components/nearby-places`): on spots the
  next spot up and down the river by distance along it (following a main river into the one it flows into, and up main
  rivers that join just above), then up to two beaches or lakes within 20 km; on beaches and lakes the three nearest
  other beaches or lakes within 30 km and the nearest river spot within 20 km, in any region. Statuses come from the
  cached region summaries. It ends with descriptive links back to the river and the region's listings.

## Quality

- Run `vendor/bin/pint --dirty --format agent`, `php artisan test --compact` and `vendor/bin/phpstan analyse` before
  finishing a change.

## Deployment

- `.github/workflows/tests.yml` runs `composer ci:check` (pint, phpstan, tests) on every push and PR. A push to `main`
  that passes then runs `dep deploy` from CI via `deployphp/action`, using the GitHub `production` environment
  (secrets `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS`; variables `DEPLOY_IP`, `DEPLOY_PORT`, `DEPLOY_USER`, `DEPLOY_PATH`).
- Pages are the same for every visitor (no cookies, sessions or accounts), so page routes send
  `cache.headers:public;max_age=60;s_maxage=300` (`$pageCaching` in `routes/web.php`) and a Cloudflare cache rule keeps
  them at the edge for five minutes; only successful responses are marked cacheable. Never add per-visitor content
  or cookies to a page without taking it out of that group. `/search` is outside it and sends `no-store`, as every
  query is its own URL. After each deploy CI purges the Cloudflare cache
  (variable `CLOUDFLARE_ZONE_ID`, secret `CLOUDFLARE_API_TOKEN`; skipped while unset), so cached pages never ask for
  the old release's build assets.
- `deploy.php` uses the Laravel recipe plus an npm build. The server clones the repo through the deploy user's
  `github-howsthewater` SSH alias. Permissions follow the shared `www-data` group model: `writable_mode` is `chmod`,
  `writable_recursive` is off, the release is made read-only after the build, and file log channels set
  `'permission' => 0660` so web requests and deploy-time artisan can share a log file.
- Every deploy runs `db:seed`, `rivers:centrelines` and `rivers:positions` after `migrate`, so a seeded river goes
  live on push as long as the GeoPackage is on the server. Gauge discovery is left to the weekly schedule.
- Server provisioning (users, PHP-FPM, nginx, deploy keys, GitHub environment) lives in the `opendata-ansible` repo.
  Nothing is deployed by hand; never run `composer update` or merge on the server.
- `bootstrap/cache` is read-only to www-data once a release is hardened; the deploy writes every cache file there as
  the deploy user. Never run `optimize:clear` or `clear-compiled` on the server: they delete `packages.php` and
  `services.php`, www-data cannot rebuild them, and every request returns a 500. After editing the server's `.env`,
  run `php artisan optimize` as deploy in the current release, which rebuilds the caches with the new values.
