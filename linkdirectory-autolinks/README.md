# Daily auto-created links (linkdirectory.pro)

Laravel artisan command `links:auto-create`. Every day it creates 15 links from
OpenStreetMap businesses that have a **name, website, phone and e-mail**:

- **One category per day.** It rotates through the category slugs mapped in
  `config/autolinks.php` (about 60 subcategories such as `cafe`, `dentist`, `hotels`).
- **One country per day.** It rotates through the countries in the site's
  `countries` table, or through the fallback list in the config if that table
  doesn't exist. If a country has fewer than 15 matches, it tops up from the
  next countries (`max_countries_per_run`).
- **Posted by admin.** Every link is saved with `owner = 1` (the admin account).
- **Published immediately.** Every link is saved with `enabled = 1` and `status = 2`.
- It skips links whose website domain is already in the directory.
- Descriptions are generated from the OSM data and end with
  "© OpenStreetMap contributors". The ODbL licence requires that attribution.

## Install (paths on the Hostinger account)

Copy the files into the app folder `domains/linkdirectory.pro/lapp/`:

| From this folder | To |
|---|---|
| `app/Console/Commands/AutoCreateLinks.php` | `lapp/app/Console/Commands/` |
| `config/autolinks.php` | `lapp/config/` |
| `database/migrations/2026_10_19_..._add_contact_columns_to_links_table.php` | `lapp/database/migrations/` |

Then check `config/autolinks.php` against the real database:

- `models`: use `\App\Link` / `\App\Category` if the app has no `app/Models` folder.
- `link_columns`: the column names the links table uses for category, phone,
  e-mail and address. The bulk-create insert shows the right names.
- `extra_columns`: any required columns, such as `status`, `user_id` or `language`.

Run once over SSH, starting with a dry run:

```bash
cd ~/domains/linkdirectory.pro/lapp
/opt/alt/php81/usr/bin/php artisan config:clear
/opt/alt/php81/usr/bin/php artisan migrate            # only adds columns that are missing
/opt/alt/php81/usr/bin/php artisan links:auto-create --dry-run
/opt/alt/php81/usr/bin/php artisan links:auto-create --category=cafe --country=AU --limit=2
```

## Schedule (hPanel → Advanced → Cron Jobs)

Daily at 03:00:

```
0 3 * * * /opt/alt/php81/usr/bin/php /home/u728060325/domains/linkdirectory.pro/lapp/artisan links:auto-create
```

Results are logged to `lapp/storage/logs/laravel.log` (`[links:auto-create]`).
