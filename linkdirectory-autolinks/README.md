# Daily auto-created links (linkdirectory.pro)

Laravel artisan command `links:auto-create`. Every day it creates 15 links from
OpenStreetMap businesses that have a **name, website, phone and e-mail**.

- **One category per day.** It rotates through the subcategories mapped in
  `config/autolinks.php` (about 60, such as `cafe`, `dentist`, `hotels`). Each
  link is filed under the subcategory and its parent category (`category_link`).
- **One country per day.** It rotates through the countries in Admin → Locations
  (top-level locations). Each link gets the country, plus the city when that
  city exists under the country (`link_location`). All of the day's links come
  from that one category and one country; if they have fewer than 15 matches,
  that day gets fewer links (set `max_countries_per_run` above 1 to top up
  from the next countries instead).
- **Saved like the bulk importer saves links:** `owner = 1`, `enabled = 1`,
  `status = 2`, and the category's language. On top of that it fills
  `phone_number` (E.164, checked the same way as the admin form), `email` and
  `address`.
- It skips businesses whose website domain is already in the directory, then
  refreshes the category and location counts.
- Descriptions are generated from the OSM data and end with
  "© OpenStreetMap contributors". The ODbL licence requires that attribution.

## Install

Copy into the live app folder `domains/linkdirectory.pro/lapp/`:

| From this folder | To |
|---|---|
| `app/Console/Commands/AutoCreateLinks.php` | `lapp/app/Console/Commands/` (create the `Commands` folder) |
| `config/autolinks.php` | `lapp/config/` |

No database changes are needed. Then, over SSH:

```bash
cd ~/domains/linkdirectory.pro/lapp
/opt/alt/php81/usr/bin/php artisan config:clear
/opt/alt/php81/usr/bin/php artisan links:auto-create --dry-run
/opt/alt/php81/usr/bin/php artisan links:auto-create --category=cafe --country=Australia --limit=2
```

Country names are matched to OpenStreetMap by their English name. If a location
title differs (e.g. "USA"), add it to `country_codes` in the config with its
ISO code.

## Schedule (hPanel → Advanced → Cron Jobs)

Daily at 03:00:

```
0 3 * * * /opt/alt/php81/usr/bin/php /home/u728060325/domains/linkdirectory.pro/lapp/artisan links:auto-create
```

Results are logged to `lapp/storage/logs/laravel.log` (`[links:auto-create]`).

## Bulk-create fix

`bulk-fix/LinkController-bulk.php` holds a replacement for the loop in
`LinkController::bulk()`. The current loop crashes the whole request when the
textarea has a blank line (usually the trailing newline), a line with fewer
than 3 `|` fields, or a category ID that doesn't exist. The file's header says
exactly what to replace.
