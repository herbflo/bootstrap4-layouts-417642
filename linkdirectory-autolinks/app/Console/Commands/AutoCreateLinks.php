<?php

namespace App\Console\Commands;

use Cviebrock\EloquentSluggable\Services\SlugService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Creates the day's links from OpenStreetMap: one category and one country
 * per day, both rotating, with title, url, description, phone and e-mail.
 *
 *   php artisan links:auto-create                 run for today
 *   php artisan links:auto-create --dry-run       show what would be created
 *   php artisan links:auto-create --category=cafe --country=AU
 */
class AutoCreateLinks extends Command
{
    protected $signature = 'links:auto-create
                            {--dry-run : Show the links without saving them}
                            {--category= : Category slug to use instead of today\'s}
                            {--country= : ISO country code to use instead of today\'s}
                            {--limit= : Number of links to create (default: config per_day)}';

    protected $description = 'Create the daily batch of links from OpenStreetMap data';

    private string $linkTable;
    private array $linkColumns;
    private ?int $adminId = null;

    public function handle(): int
    {
        $cfg = config('autolinks');
        $limit = (int) ($this->option('limit') ?: $cfg['per_day']);
        $dryRun = (bool) $this->option('dry-run');

        $linkModel = $cfg['models']['link'];
        $this->linkTable = (new $linkModel)->getTable();
        $this->linkColumns = Schema::getColumnListing($this->linkTable);

        $this->adminId = $this->adminId($cfg);
        if ($this->adminId === null) {
            $this->error('No admin user found. Set posted_by.user_id in config/autolinks.php.');
            return self::FAILURE;
        }
        $this->info("Posting as admin user #{$this->adminId}");

        $category = $this->pickCategory($cfg);
        if (!$category) {
            $this->error('No category from config/autolinks.php exists in the database.');
            return self::FAILURE;
        }

        $countries = $this->countries($cfg);
        if (!$countries) {
            $this->error('No countries found.');
            return self::FAILURE;
        }

        $day = intdiv(now()->startOfDay()->timestamp, 86400);
        $start = $day % count($countries);
        if ($code = strtoupper((string) $this->option('country'))) {
            $start = array_search($code, array_column($countries, 'code'), true);
            if ($start === false) {
                $this->error("Country {$code} is not in the site's country list.");
                return self::FAILURE;
            }
        }

        $filters = $cfg['categories'][$category['slug']];
        $created = [];
        $attempts = min(max(1, (int) $cfg['max_countries_per_run']), count($countries));

        for ($i = 0; $i < $attempts && count($created) < $limit; $i++) {
            $country = $countries[($start + $i) % count($countries)];
            $this->info("Category: {$category['slug']} | Country: {$country['name']}");

            $places = $this->fetchPlaces($filters, $country, $cfg);
            shuffle($places);

            foreach ($places as $place) {
                if (count($created) >= $limit) {
                    break;
                }
                $row = $this->buildRow($place, $category['model'], $country, $cfg);
                if (!$row || $this->exists($row['url'])) {
                    continue;
                }
                if (!$dryRun) {
                    DB::table($this->linkTable)->insert($row);
                }
                $created[] = $row;
            }
        }

        if ($created) {
            $this->table(['title', 'url', 'phone', 'email'], array_map(fn ($r) => [
                $r['title'], $r['url'], $r[$cfg['link_columns']['phone']] ?? '', $r[$cfg['link_columns']['email']] ?? '',
            ], $created));
        }

        $msg = sprintf('%s %d link(s) for category "%s".', $dryRun ? 'Would create' : 'Created', count($created), $category['slug']);
        $this->info($msg);
        Log::info("[links:auto-create] {$msg}");

        return self::SUCCESS;
    }

    /** Today's category: the next configured slug that exists in the database. */
    private function pickCategory(array $cfg): ?array
    {
        $model = $cfg['models']['category'];
        $slugCol = $cfg['category_slug_column'];
        $slugs = array_keys($cfg['categories']);

        if ($wanted = $this->option('category')) {
            if (!isset($cfg['categories'][$wanted])) {
                $this->error("Category {$wanted} has no OSM mapping in config/autolinks.php.");
                return null;
            }
            $slugs = [$wanted];
        }

        $found = $model::whereIn($slugCol, $slugs)->get()->keyBy($slugCol);
        $available = array_values(array_filter($slugs, fn ($s) => $found->has($s)));
        if (!$available) {
            return null;
        }

        $slug = $available[intdiv(now()->startOfDay()->timestamp, 86400) % count($available)];
        return ['slug' => $slug, 'model' => $found[$slug]];
    }

    /** The admin account the links are posted by. */
    private function adminId(array $cfg): ?int
    {
        if ($id = $cfg['posted_by']['user_id']) {
            return (int) $id;
        }
        if (!Schema::hasTable('users')) {
            return null;
        }

        $cols = Schema::getColumnListing('users');
        $query = DB::table('users')->orderBy('id');

        if (in_array('is_admin', $cols, true)) {
            $id = (clone $query)->where('is_admin', 1)->value('id');
        } else {
            $roleCol = $this->firstColumn($cols, ['role', 'type', 'user_type', 'group']);
            $id = $roleCol ? (clone $query)->whereIn($roleCol, ['admin', 'administrator'])->value('id') : null;
        }

        $id = $id ?? $query->value('id');
        return $id !== null ? (int) $id : null;
    }

    /** The site's countries as [code, name, id], from the countries table or the fallback list. */
    private function countries(array $cfg): array
    {
        $table = $cfg['countries']['table'];

        if ($table && Schema::hasTable($table)) {
            $cols = Schema::getColumnListing($table);
            $codeCol = $this->firstColumn($cols, ['iso_code', 'iso2', 'iso', 'country_code', 'code', 'sortname']);
            $nameCol = $this->firstColumn($cols, ['name', 'title', 'country', 'country_name']);

            if ($nameCol) {
                $rows = DB::table($table)->orderBy('id')->get();
                $list = [];
                foreach ($rows as $row) {
                    $code = $codeCol ? strtoupper(trim((string) $row->$codeCol)) : '';
                    $list[] = [
                        'id' => $row->id ?? null,
                        'code' => strlen($code) === 2 ? $code : '',
                        'name' => (string) $row->$nameCol,
                    ];
                }
                if ($list) {
                    return $list;
                }
            }
        }

        $list = [];
        foreach ($cfg['countries']['fallback'] as $code => $name) {
            $list[] = ['id' => null, 'code' => strtoupper($code), 'name' => $name];
        }
        return $list;
    }

    private function fetchPlaces(array $filters, array $country, array $cfg): array
    {
        $area = $country['code'] !== ''
            ? sprintf('area["ISO3166-1"="%s"][admin_level=2]->.c;', $country['code'])
            : sprintf('area["name:en"="%s"][admin_level=2]->.c;', addcslashes($country['name'], '"\\'));

        $must = '[name][~"^(website|contact:website)$"~"."]';
        if ($cfg['require_phone']) {
            $must .= '[~"^(phone|contact:phone)$"~"."]';
        }
        if ($cfg['require_email']) {
            $must .= '[~"^(email|contact:email)$"~"."]';
        }

        $union = '';
        foreach ($filters as $filter) {
            $union .= "nwr{$filter}{$must}(area.c);\n";
        }

        $o = $cfg['overpass'];
        $query = "[out:json][timeout:{$o['timeout']}];\n{$area}\n(\n{$union});\nout tags {$o['fetch_limit']};";

        foreach ($o['endpoints'] as $endpoint) {
            try {
                $res = Http::withHeaders(['User-Agent' => $o['user_agent']])
                    ->timeout($o['timeout'] + 20)
                    ->asForm()
                    ->post($endpoint, ['data' => $query]);

                if ($res->successful()) {
                    return $res->json('elements') ?? [];
                }
                $this->warn("Overpass {$endpoint} returned HTTP {$res->status()}");
            } catch (\Throwable $e) {
                $this->warn("Overpass {$endpoint} failed: {$e->getMessage()}");
            }
            sleep(5);
        }

        Log::warning("[links:auto-create] Overpass gave no data for {$country['name']}");
        return [];
    }

    private function buildRow(array $place, $category, array $country, array $cfg): ?array
    {
        $t = $place['tags'] ?? [];
        $title = trim($t['name'] ?? '');
        $url = $this->normalizeUrl($t['website'] ?? $t['contact:website'] ?? '');
        $phone = $this->firstValue($t['phone'] ?? $t['contact:phone'] ?? '');
        $email = strtolower($this->firstValue($t['email'] ?? $t['contact:email'] ?? ''));

        if ($title === '' || !$url) {
            return null;
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = '';
        }
        if (($cfg['require_phone'] && $phone === '') || ($cfg['require_email'] && $email === '')) {
            return null;
        }

        $address = $this->address($t);
        $catName = $category->title ?? $category->name ?? Str::headline($category->{$cfg['category_slug_column']});
        $description = $this->description($title, $catName, $t, $country['name']);

        $cols = $cfg['link_columns'];
        $row = [
            'title' => Str::limit($title, 250, ''),
            'description' => $description,
            'slug' => SlugService::createSlug($cfg['models']['link'], 'slug', $title),
            'url' => $url,
            'bypass_redirect' => 0,
            'bypass_details' => 0,
            'page_views' => 0,
            'clicks' => 0,
            'featured_category' => 0,
            'featured_home' => 0,
            'votes' => '0.00',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($cols['category']) {
            $row[$cols['category']] = $category->id;
        }
        if ($cols['phone']) {
            $row[$cols['phone']] = preg_replace('/[^\d+]/', '', $phone);
        }
        if ($cols['email']) {
            $row[$cols['email']] = $email;
        }
        if ($cols['address'] && $address !== '') {
            $row[$cols['address']] = $address;
        }

        $countryCol = $cols['country'] ?? $this->firstColumn($this->linkColumns, ['country_id', 'location_id', 'country']);
        if ($countryCol) {
            $row[$countryCol] = Str::endsWith($countryCol, '_id') ? $country['id'] : $country['name'];
        }

        $authorCol = $cfg['posted_by']['column']
            ?? $this->firstColumn($this->linkColumns, ['user_id', 'created_by', 'author_id', 'posted_by', 'added_by']);
        if ($authorCol && $this->adminId !== null) {
            $row[$authorCol] = $this->adminId;
        }

        $row = array_merge($row, $cfg['extra_columns']);

        // Only keep columns the links table really has.
        return array_intersect_key($row, array_flip($this->linkColumns)) + ['url' => $url, 'title' => $row['title']];
    }

    private function description(string $title, string $catName, array $t, string $countryName): string
    {
        $city = $t['addr:city'] ?? $t['addr:suburb'] ?? $t['addr:town'] ?? '';
        $where = $city !== '' ? "{$city}, {$countryName}" : $countryName;

        $text = "{$title} – {$catName} in {$where}.";
        if (!empty($t['description'])) {
            $text .= ' ' . trim($t['description']);
        }
        if (!empty($t['opening_hours'])) {
            $text .= " Opening hours: {$t['opening_hours']}.";
        }
        // OpenStreetMap data is ODbL-licensed and must be attributed.
        return $text . ' Business details © OpenStreetMap contributors.';
    }

    private function address(array $t): string
    {
        $street = trim(($t['addr:housenumber'] ?? '') . ' ' . ($t['addr:street'] ?? ''));
        $parts = array_filter([
            $street,
            $t['addr:suburb'] ?? $t['addr:city'] ?? '',
            trim(($t['addr:state'] ?? '') . ' ' . ($t['addr:postcode'] ?? '')),
        ]);
        return implode(', ', $parts);
    }

    private function exists(string $url): bool
    {
        $host = preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST));
        return DB::table($this->linkTable)
            ->where('url', $url)
            ->orWhere('url', 'like', '%://' . $host . '%')
            ->orWhere('url', 'like', '%://www.' . $host . '%')
            ->exists();
    }

    private function normalizeUrl(string $url): ?string
    {
        $url = trim($this->firstValue($url));
        if ($url === '') {
            return null;
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        return filter_var($url, FILTER_VALIDATE_URL) ? rtrim($url, '/') : null;
    }

    /** OSM allows several values separated by ";" — keep the first. */
    private function firstValue(string $value): string
    {
        return trim(explode(';', $value)[0]);
    }

    private function firstColumn(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $c) {
            if (in_array($c, $columns, true)) {
                return $c;
            }
        }
        return null;
    }
}
