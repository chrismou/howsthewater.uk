# How's the Water?

[howsthewater.uk](https://howsthewater.uk) shows upstream storm overflow spills and river levels for popular swimming,
paddling and fishing spots on Yorkshire's rivers. Pick a spot and it tells you which sewage overflows upstream have
discharged recently, whether any are discharging now, and what the nearest river gauge is reading.

It reports what the monitors say. It does not say whether the water is safe or clean, and all copy on the site should
keep that distinction.

## What it does

- Polls Yorkshire Water's near real-time storm overflow feed (via the Stream open data hub) every 15 minutes and builds
  a spill history, since the feed only exposes each monitor's latest event.
- Polls Environment Agency river level gauges every 15 minutes.
- Positions every monitor, gauge and spot along the river centreline (from OS Open Rivers), so "upstream" means
  further up the same river rather than just nearby.
- Shows a 30-day history per spot: minutes of upstream spilling per day and the individual events.
- Serves a page per river (`/yorkshire/wharfe`) and per spot (`/yorkshire/wharfe/otley`), plus a read-only JSON API at
  `/api/v1` built from the same services.

Covered rivers are seeded in `database/seeders/RiverSeeder.php` and spots in `SpotSeeder.php`. The main rivers are the
Wharfe, Aire, Calder, Don, Nidd, Ure, Swale, Derwent, Ouse, Esk and Hull, each with the named tributaries that carry
Yorkshire Water monitors (the Colne, Holme, Rother, Dearne, Sheaf, Loxley, Rye, Foss, Skell and around fifty becks and
brooks). Tributaries don't get pages; their monitors count as upstream of any spot below the confluence, and distances
follow the water down the tributary and then the main river.

Spot conditions are cached for up to 30 minutes and flushed by every ingest and positioning run, so pages and the API
are cheap to serve. Set `CACHE_STORE=redis` in production if Redis is available; the database store works fine too.

## Data sources

All open data, no API keys required.

| Source | Used for | Licence |
| --- | --- | --- |
| Stream / Yorkshire Water storm overflow activity (ArcGIS feature service) | Overflow status and spill events | OGL v3 |
| Environment Agency real-time flood monitoring API | Gauge stations and 15-minute level readings | OGL v3 |
| OS Open Rivers (GeoPackage download) | River centrelines for upstream positioning | OGL v3 |
| OpenStreetMap | Map tiles, and a fallback centreline source via Overpass | ODbL |

## Requirements

- PHP 8.4+, Composer
- Node 22+ and npm
- MySQL 8

## Local setup

```sh
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Set the `DB_*` values in `.env`, then:

```sh
php artisan migrate --seed
npm run build            # or `composer run dev` for Vite with hot reload
```

### River centrelines

Download the GB GeoPackage from the OS Data Hub (OS Open Rivers, about 50 MB zipped) and put `oprvrs_gb.gpkg` at
`storage/app/private/oprvrs_gb.gpkg`, or point `OS_OPEN_RIVERS_PATH` in `.env` at it. The file is gitignored.

```sh
php artisan rivers:centrelines
```

If the GeoPackage isn't available, `php artisan rivers:centrelines --source=osm` fetches from OpenStreetMap instead,
but Overpass is slow and often rejects requests.

### First data load

Run these once, in this order:

```sh
php artisan ingest:overflows          # all monitors and their latest spill events
php artisan ingest:gauges --stations  # discover gauges on each river, backfill 7 days of readings
php artisan ingest:rainfall --stations # find rain gauges near spots, link the nearest, backfill 7 days
php artisan rivers:centrelines        # as above
php artisan rivers:positions          # place monitors, gauges and spots along each river
```

After that the site has data. Start it with `php artisan serve` or `composer run dev`.

## Scheduled commands

The schedule lives in `routes/console.php`. In production, run the Laravel scheduler from cron every minute:

```
* * * * * cd /path/to/howsthewater && php artisan schedule:run >> /dev/null 2>&1
```

| Command | Frequency | What it does |
| --- | --- | --- |
| `ingest:overflows` | Every 15 minutes | Refresh overflow status, open and close spill events |
| `ingest:gauges` | Every 15 minutes | Fetch new level readings since the last stored one |
| `ingest:gauges --stations` | Weekly, Monday 03:00 | Pick up new or renamed gauge stations |
| `ingest:rainfall` | Every 15 minutes | Fetch new 15 minute rainfall totals from the rain gauges spots use |
| `ingest:rainfall --stations` | Weekly, Monday 03:15 | Refresh the rain gauges near spots and relink each spot to the nearest |
| `rivers:positions` | Daily, 03:30 | Position any newly added monitors, gauges or spots |

`rivers:centrelines` only needs running when a river is added or the OS data is updated.

## Adding a river, tributary or spot

1. Add the river to `RiverSeeder` with the receiving watercourse names the overflow feed uses as `aliases`. Names are
   matched after stripping qualifiers like "(Culverted)", and "Trib Of River Aire" counts as the Aire.
2. Name it as the OS Open Rivers data does. A main river needs an approximate `mouth` lat/lng, because names like
   Calder, Derwent and Ouse are shared with rivers elsewhere in Britain and the import keeps the one ending nearest it.
   For a tributary, set `parent` to the parent river's slug instead; several becks can share a name and the import
   picks the one whose mouth is nearest the parent river. Fragments split by reservoirs or a brief change of name are
   joined when they sit within a few kilometres of each other.
3. Add spots to `SpotSeeder`, source to mouth, with the lat/lng of the bank access point.
4. Run `php artisan db:seed`, then `rivers:centrelines`, `ingest:gauges --stations` and `rivers:positions`. The
   positions step also records where each tributary joins its parent and links each spot to its nearest gauge,
   preferring gauges that report stage in metres over ones reporting height above sea level (mAOD).
5. Push. The deploy runs the seeders, centrelines and positions itself (the GeoPackage must already be on the
   server). Gauges for the new river are picked up by the weekly `ingest:gauges --stations` run, or sooner if you
   run it by hand.

## API

- `GET /api/v1/regions`: regions with their rivers and links.
- `GET /api/v1/regions/{region}`: the region summary: headline numbers plus every river and spot with its spill status,
  level and rainfall, refreshed after each ingest run.
- `GET /api/v1/regions/{region}/rivers` and `GET /api/v1/regions/{region}/rivers/{river}`: river overview with every
  spot's conditions.
- `GET /api/v1/regions/{region}/spots` and `GET /api/v1/regions/{region}/rivers/{river}/spots/{spot}`: a spot's spill
  summary, upstream monitors, gauge level and rainfall at the nearest rain gauge (`rainfall` is null when none is
  within 20 km).

River and spot slugs are only unique within their region and river, since names repeat across Britain.

Responses carry `Cache-Control: public, max-age=300` and an ETag, so a CDN in front of the site can cache them.

Every page carries Open Graph and Twitter card tags with a shared image at `/og-image.png` (rendered from
`resources/images/og-image.svg` with Inkscape), and `/sitemap.xml` lists the home, region, river and spot pages.

`spills.offline` counts upstream monitors reporting offline; when all of them are, `spills.recency` is `offline`.
`data_stale` is true when the overflow feed behind a spot's upstream monitors has not updated for 6 hours, and
`level.stale` when the gauge's latest reading is that old. The site shows a warning in the same cases; treat the
spill status and level as a snapshot from `data_updated_at` or `level.measured_at` rather than current.

## Development

```sh
php artisan test --compact
vendor/bin/pint --dirty
vendor/bin/phpstan analyse
```

Architecture notes for contributors (and AI agents) are in `CLAUDE.md`.
