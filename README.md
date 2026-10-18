# How's the Water?

[howsthewater.uk](https://howsthewater.uk) shows upstream storm overflow spills and river levels for popular swimming,
paddling and fishing spots on rivers in Yorkshire, the Thames Valley, London, the North West, the North East, the Midlands, the East of England, the South West, Wessex, the South East, Scotland and Wales. Pick a spot and it tells you which sewage overflows upstream have
discharged recently, whether any are discharging now, and what the nearest river gauge is reading.

It reports what the monitors say. It does not say whether the water is safe or clean, and all copy on the site should
keep that distinction.

## What it does

- Polls the water companies' near real-time storm overflow feeds (Yorkshire Water, Thames Water, United Utilities, Northumbrian Water and Severn Trent via
  the Stream open data hub, and Scottish Water from its own API) every 15 minutes and builds
  a spill history, since the feed only exposes each monitor's latest event.
- Polls river level and rain gauges every 15 minutes: the Environment Agency's in England and SEPA's in Scotland.
- Polls the Environment Agency's flood alerts and warnings every 15 minutes and shows any in force for a flood area
  covering a spot on its page, in the live bar and on the home and region pages, with a link to GOV.UK.
- Positions every monitor, gauge and spot along the river centreline (from OS Open Rivers), so "upstream" means
  further up the same river rather than just nearby.
- Shows a 30-day history per spot: minutes of upstream spilling per day and the individual events.
- Covers designated beaches and lakes from the Environment Agency bathing water register (Natural Resources Wales's in
  Wales, and SEPA's map service in Scotland, classifications only, linking to SEPA's results): annual classifications,
  weekly sample counts and daily pollution risk forecasts in the bathing season, lake levels, and storm overflows
  within 3 km. River spots that are themselves designated bathing waters show the same record.
- Offers an RSS feed per spot and river (`/yorkshire/rivers/wharfe/otley/feed`, `/yorkshire/rivers/wharfe/feed`): overflows upstream
  starting and stopping, and flood warnings in force.
- Serves a page per river (`/yorkshire/rivers/wharfe`) and per spot (`/yorkshire/rivers/wharfe/otley`), plus a read-only JSON API at
  `/api/v1` built from the same services.

Covered rivers are seeded by region in `database/seeders/RiverSeeder.php` and spots in `SpotSeeder.php`. Yorkshire's
main rivers are the Wharfe, Aire, Calder, Don, Nidd, Ure, Swale, Derwent, Ouse, Esk and Hull. The Thames Valley has the
Thames from its source to the tidal limit at Teddington, with the twenty-four tributaries that join above it (Churn, Coln,
Windrush, Evenlode, Cherwell, Thame, Kennet and Lambourn, Loddon, the Colne with the Ver, Gade and Chess, Wey and Cranleigh
Waters, Mole and others). London has Thames Water's rivers that join the tidal Thames below Teddington: the Lea (with the
Stort, Rib, Ash, Beane, Mimram and Pymme's Brook), Roding, Brent, Crane, Wandle, Beverley Brook, Ravensbourne and Kent's
Darent; its river pages also link the Thames, as Scotland's link the Tweed. The North West has the Ribble, Lune, Kent, Eden and Cumbrian Derwent, with the tributaries that
hold United Utilities monitors (the Lancashire Calder with Pendle Water, the Darwen and Blakewater, the Wenning, two
Gretas, the Eamont, Irthing, Petteril and Caldew and others). The North East has the Tyne, with the North and South
Tyne flowing into it, the Wear, Tees, Coquet, Aln, Wansbeck, Blyth and the Tweed, with the Till flowing into it. The
Tweed's overflows come from Scottish Water as well as Northumbrian Water, and its Scottish levels and rainfall from SEPA. The Midlands has the Trent, with the Soar and the Peak District's
Derwent, Wye, Dove and Manifold flowing into it, and the Severn with the Teme and Warwickshire Avon. The East of England has Anglian Water's
rivers: the Great Ouse (with the Cam, Lark and Little Ouse), Nene, Welland, Witham, the Broads (Yare, Wensum, Bure and Waveney), the Stour,
Gipping, Deben, Alde, Colne, Blackwater, Chelmer and Crouch, plus Rutland Water and beaches from Cleethorpes to Southend. The South West has
South West Water's rivers in Devon and Cornwall: the Dart and East Dart, Teign, Plym, Erme, Avon, Exe and Barle, Tamar and Tavy, Fowey, Camel,
Taw, Torridge, East Lyn, Otter and Axe, and its beaches. Wessex has Wessex Water's rivers: the Bristol Avon with the
Somerset Frome, the Parrett with the Tone, the Brue, the Dorset Stour, Frome and Piddle, and the Hampshire Avon, plus
Henleaze Lake and beaches from Weston-super-Mare to Bournemouth. The South East has Southern Water's rivers: the
Medway, Great Stour, Rother, Cuckmere, Sussex Ouse, Adur and Arun, the Itchen, Test, Meon and Lymington River, and the
Isle of Wight's Medina and Eastern Yar, with beaches from the New Forest round the coast to Whitstable and Sheppey. Scotland has the Tay (with the Tummel, Garry, Braan and Earn), Spey,
Ness, Findhorn, Lossie, Dee, Don, Forth (with the Teith, Allan Water and Devon), Water of Leith, Almond, Esk, Tyne, Clyde (with the Kelvin
and White Cart), Leven, Irvine, Ayr, Doon, Fife Eden, Angus North and South Esk, Nith, Annan and Galloway Dee, from Scottish Water's overflows and SEPA's levels and rainfall. Wales has the Taff, Usk,
Wye (with the Irfon, Ithon, Lugg and Monnow), Tawe, Neath (with the Mellte), Towy, Teifi, Western Cleddau, Dovey, Mawddach
(with the Wnion), Glaslyn, Conwy (with the Llugwy) and Dee (with the Tryweryn), with Natural Resources Wales's levels,
rainfall, flood warnings and bathing waters, and Dŵr Cymru Welsh Water's storm overflow map. A main river that joins another (the Ure into the Ouse, the North
Tyne into the Tyne) keeps its own pages, and its overflows count upstream of the river it joins. Each main river carries the named
tributaries that hold water company monitors (the Colne, Holme, Rother, Dearne, Sheaf, Loxley, Rye, Foss, Skell and around fifty becks and
brooks). Tributaries don't get pages; their monitors count as upstream of any spot below the confluence, and distances
follow the water down the tributary and then the main river.

Spot conditions are cached for up to 30 minutes and flushed by every ingest and positioning run, so pages and the API
are cheap to serve. Set `CACHE_STORE=redis` in production if Redis is available; the database store works fine too.

## Data sources

All open data. Only Natural Resources Wales needs API keys (free, one per product, from its API portal).

| Source | Used for | Licence |
| --- | --- | --- |
| Stream storm overflow activity, one ArcGIS feature service per water company | Overflow status and spill events | CC BY 4.0 |
| Scottish Water near real-time overflow event monitoring API | Overflow status and spill events in Scotland | Open reuse, credit Scottish Water |
| Environment Agency real-time flood monitoring API | Gauge stations and 15-minute level readings | OGL v3 |
| SEPA time series API (KiWIS) | Scottish level and rain gauges and their 15-minute readings | OGL v3 |
| SEPA bathing waters map service (ArcGIS) | Scottish bathing water locations and classifications | OGL v3 |
| Natural Resources Wales bathing water register (on the Environment Agency's data platform) | Welsh bathing water classifications, samples and forecasts | OGL v3 |
| Natural Resources Wales API (River level, Rainfall and Sea data; Live Flood Warnings), key required | Welsh level and rain gauges, 15-minute readings, flood alerts and warnings (kept for a year at most) | OGL v3 |
| DataMapWales (NRW flood warning and alert areas, WFS) | Welsh flood area outlines, to link spots to warnings | OGL v3 |
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
php artisan ingest:rainfall --stations # find rain gauges near spots, beaches and lakes, link the nearest, backfill 7 days
php artisan ingest:floods             # link spots to EA flood areas (slow: one request per spot), fetch warnings and record their history
php artisan ingest:bathing-waters     # beaches, lakes and their bathing water history (a couple of minutes)
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
| `ingest:overflows` | Every 15 minutes | Refresh overflow status, open and close spill events, position monitors seen for the first time |
| `ingest:gauges` | Every 15 minutes | Fetch new level readings, and discover gauges on any river never searched |
| `ingest:gauges --stations` | Weekly, Monday 03:05 | Re-check every river for new or renamed gauge stations |
| `ingest:rainfall` | Every 15 minutes | Fetch new rainfall totals, and find rain gauges for any spot, beach or lake never searched |
| `ingest:rainfall --stations` | Weekly, Monday 03:20 | Refresh the rain gauges near spots and relink each spot to the nearest |
| `ingest:floods` | Every 15 minutes | Fetch the flood alerts and warnings in force, record the history of every warning in England, and link any spot never searched to its flood areas |
| `ingest:floods --areas` | Weekly, Monday 03:50 | Re-check the flood areas covering every spot |
| `ingest:bathing-waters` | Hourly | Refresh bathing water classifications, samples and forecasts; backfill history for new sites; link lakes to their level gauge |
| `ingest:bathing-waters --history` | Weekly, Monday 04:20 | Refetch sample and classification history for every bathing water |
| `regions:refresh` | Every 15 minutes, after the ingests | Rebuild cached conditions, summaries and menus for regions an ingest changed, in place |
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
   server). The next scheduled ingests catch up on the rest without a manual step: the overflow ingest positions a new
   region's monitors when it first sees them, the gauge ingest discovers and backfills gauges on rivers never searched,
   and the rainfall ingest finds rain gauges for spots never searched. The first of those runs can take several
   minutes while it backfills a week of readings; it runs in the scheduler, so deploys are not delayed.

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

## Changing production settings

The deploy makes each release read-only to the web server, including `bootstrap/cache`, after writing every cache file
Laravel needs. After editing the server's `.env`, run `php artisan optimize` as the deploy user in the current release.
Do not run `php artisan optimize:clear` there: it deletes cache files the web server cannot recreate, and the site
returns a 500 until `php artisan optimize` is run or the next deploy.
