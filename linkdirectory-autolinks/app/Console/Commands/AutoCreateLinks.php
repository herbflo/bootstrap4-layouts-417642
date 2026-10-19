<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Link;
use App\Models\Location;
use Brick\PhoneNumber\PhoneNumber;
use Brick\PhoneNumber\PhoneNumberFormat;
use Carbon\Carbon;
use Cviebrock\EloquentSluggable\Services\SlugService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Creates the day's links from OpenStreetMap: one category and one country
 * per day, both rotating, with title, url, description, phone and e-mail.
 *
 *   php artisan links:auto-create                 run for today
 *   php artisan links:auto-create --dry-run       show what would be created
 *   php artisan links:auto-create --category=cafe --country=Australia --limit=2
 */
class AutoCreateLinks extends Command
{
    protected $signature = 'links:auto-create
                            {--dry-run : Show the links without saving them}
                            {--category= : Category slug to use instead of today\'s}
                            {--country= : Country (location title) to use instead of today\'s}
                            {--limit= : Number of links to create (default: config per_day)}';

    protected $description = 'Create the daily batch of links from OpenStreetMap data';

    public function handle(): int
    {
        $cfg = config('autolinks');
        $limit = (int) ($this->option('limit') ?: $cfg['per_day']);
        $dryRun = (bool) $this->option('dry-run');
        $day = intdiv(Carbon::today()->timestamp, 86400);

        $category = $this->pickCategory($cfg, $day);
        if (!$category) {
            return self::FAILURE;
        }

        // Countries are the top-level locations in the category's language.
        $countries = Location::where('parent_id', 0)
            ->where('language', $category->language)
            ->orderBy('id')
            ->get()
            ->values();

        if ($countries->isEmpty()) {
            $this->error('No countries in Admin > Locations for this language.');
            return self::FAILURE;
        }

        $start = $day % $countries->count();
        if ($wanted = $this->option('country')) {
            $start = $countries->search(fn ($c) => strcasecmp($c->title, $wanted) === 0);
            if ($start === false) {
                $this->error("Country {$wanted} is not in Admin > Locations.");
                return self::FAILURE;
            }
        }

        $parent = $category->parent_id ? Category::find($category->parent_id) : null;
        $categoryIds = array_values(array_filter([$category->id, $parent->id ?? null]));
        $filters = $cfg['categories'][$category->slug];
        $attempts = min(max(1, (int) $cfg['max_countries_per_run']), $countries->count());
        $created = [];

        for ($i = 0; $i < $attempts && count($created) < $limit; $i++) {
            $country = $countries[($start + $i) % $countries->count()];
            $this->info("Category: {$category->title} | Country: {$country->title}");

            $places = $this->fetchPlaces($filters, $country->title, $cfg);
            shuffle($places);

            foreach ($places as $place) {
                if (count($created) >= $limit) {
                    break;
                }
                $data = $this->linkData($place['tags'] ?? [], $category, $country, $cfg);
                if (!$data || $this->exists($data['url'])) {
                    continue;
                }

                if (!$dryRun) {
                    $this->save($data, $categoryIds, $country, $data['city']);
                }
                $created[] = $data;
            }
        }

        if ($created && !$dryRun) {
            // Same as the bulk importer: refresh the link counts.
            count_categories();
            count_locations();
        }

        if ($created) {
            $this->table(['title', 'url', 'phone', 'email'], array_map(
                fn ($d) => [$d['title'], $d['url'], $d['phone_number'], $d['email']],
                $created
            ));
        }

        $msg = sprintf('%s %d link(s) for category "%s".', $dryRun ? 'Would create' : 'Created', count($created), $category->title);
        $this->info($msg);
        Log::info("[links:auto-create] {$msg}");

        return self::SUCCESS;
    }

    /** Today's category: the next configured subcategory slug that exists in the database. */
    private function pickCategory(array $cfg, int $day): ?Category
    {
        $slugs = array_keys($cfg['categories']);

        if ($wanted = $this->option('category')) {
            if (!isset($cfg['categories'][$wanted])) {
                $this->error("Category {$wanted} has no OpenStreetMap mapping in config/autolinks.php.");
                return null;
            }
            $slugs = [$wanted];
        }

        $found = Category::whereIn('slug', $slugs)->get()->keyBy('slug');
        $available = array_values(array_filter($slugs, fn ($s) => $found->has($s)));

        if (!$available) {
            $this->error('None of the categories in config/autolinks.php exist in Admin > Categories.');
            return null;
        }

        return $found[$available[$day % count($available)]];
    }

    private function fetchPlaces(array $filters, string $countryTitle, array $cfg): array
    {
        $code = $cfg['country_codes'][$countryTitle] ?? null;
        $area = $code
            ? sprintf('area["ISO3166-1"="%s"][admin_level=2]->.c;', $code)
            : sprintf('area["name:en"="%s"][admin_level=2]->.c;', addcslashes($countryTitle, '"\\'));

        // Plain tag filters only: regex key filters ([~"^(phone|...)$"~"."]) over a
        // whole country overload the public servers. Phone and e-mail are
        // checked in PHP instead (linkData).
        $union = '';
        foreach ($filters as $filter) {
            $union .= "nwr{$filter}[name][\"website\"](area.c);\n";
            $union .= "nwr{$filter}[name][\"contact:website\"](area.c);\n";
        }

        $o = $cfg['overpass'];
        $query = "[out:json][timeout:{$o['timeout']}];\n{$area}\n(\n{$union});\nout tags {$o['fetch_limit']};";

        foreach ($o['endpoints'] as $endpoint) {
            for ($try = 1; $try <= $o['tries_per_endpoint']; $try++) {
                try {
                    $res = Http::withHeaders(['User-Agent' => $o['user_agent']])
                        ->timeout($o['timeout'] + 20)
                        ->asForm()
                        ->post($endpoint, ['data' => $query]);

                    if ($res->successful()) {
                        return $res->json('elements') ?? [];
                    }
                    $this->warn("Overpass {$endpoint} returned HTTP {$res->status()} (try {$try}): "
                        . Str::limit(trim(strip_tags($res->body())), 150));
                } catch (\Throwable $e) {
                    $this->warn("Overpass {$endpoint} failed (try {$try}): {$e->getMessage()}");
                }
                // Busy server: wait longer before each retry (15s, 30s, ...).
                sleep(15 * $try);
            }
        }

        Log::warning("[links:auto-create] Overpass gave no data for {$countryTitle}");
        return [];
    }

    /** Map OSM tags to the link fields, or null when a required field is missing. */
    private function linkData(array $t, Category $category, Location $country, array $cfg): ?array
    {
        $title = trim($t['name'] ?? '');
        $url = $this->normalizeUrl($t['website'] ?? $t['contact:website'] ?? '');
        if ($title === '' || !$url) {
            return null;
        }

        $email = strtolower($this->firstValue($t['email'] ?? $t['contact:email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = null;
        }

        $phone = $this->phone($this->firstValue($t['phone'] ?? $t['contact:phone'] ?? ''), $t['addr:country'] ?? null);

        if (($cfg['require_phone'] && !$phone) || ($cfg['require_email'] && !$email)) {
            return null;
        }

        $city = $t['addr:city'] ?? $t['addr:town'] ?? $t['addr:suburb'] ?? null;

        return [
            'title' => Str::limit($title, 100, ''),
            'url' => $url,
            'description' => Str::limit($this->description($title, $category->title, $t, $city, $country->title), 755, ''),
            'phone_number' => $phone,
            'email' => $email,
            'address' => $this->address($t),
            'city' => $city,
        ];
    }

    /** Insert the link the same way LinkController@bulk does, plus contacts and locations. */
    private function save(array $data, array $categoryIds, Location $country, ?string $city): void
    {
        DB::transaction(function () use ($data, $categoryIds, $country, $city) {
            $category = Category::find($categoryIds[0]);

            $linkId = Link::insertGetId([
                'title' => $data['title'],
                'description' => $data['description'],
                'slug' => SlugService::createSlug(Link::class, 'slug', $data['title']),
                'url' => $data['url'],
                'phone_number' => $data['phone_number'],
                'email' => $data['email'],
                'address' => $data['address'],
                'bypass_redirect' => 0,
                'bypass_details' => 0,
                'page_views' => 0,
                'clicks' => 0,
                'featured_category' => 0,
                'featured_home' => 0,
                'votes' => '0.00',
                'total_votes' => 0,
                'badge' => 0,
                'owner' => config('autolinks.owner'),
                'package' => 0,
                'gallery' => '',
                'language' => $category->language,
                'enabled' => 1,
                'status' => 2,
                'is_subscription' => 0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            foreach ($categoryIds as $categoryId) {
                DB::table('category_link')->insert(['link_id' => $linkId, 'category_id' => $categoryId]);
            }

            // Country, plus the city when it exists under that country in Admin > Locations.
            $locationIds = [$country->id];
            if ($city) {
                $cityId = Location::where('parent_id', $country->id)->where('title', $city)->value('id');
                if ($cityId) {
                    $locationIds[] = $cityId;
                }
            }
            foreach ($locationIds as $locationId) {
                DB::table('link_location')->insert(['link_id' => $linkId, 'location_id' => $locationId]);
            }
        });
    }

    private function description(string $title, string $catName, array $t, ?string $city, string $countryName): string
    {
        $where = $city ? "{$city}, {$countryName}" : $countryName;

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

    private function address(array $t): ?string
    {
        $street = trim(($t['addr:housenumber'] ?? '') . ' ' . ($t['addr:street'] ?? ''));
        $parts = array_filter([
            $street,
            $t['addr:suburb'] ?? $t['addr:city'] ?? '',
            trim(($t['addr:state'] ?? '') . ' ' . ($t['addr:postcode'] ?? '')),
        ]);
        return $parts ? implode(', ', $parts) : null;
    }

    /** E.164 phone number, validated the same way the admin link form does. */
    private function phone(string $raw, ?string $region): ?string
    {
        if ($raw === '') {
            return null;
        }
        try {
            $number = PhoneNumber::parse($raw, $region ? strtoupper($region) : null);
            return $number->format(PhoneNumberFormat::E164);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function exists(string $url): bool
    {
        $host = preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST));
        return DB::table('links')
            ->where('url', $url)
            ->orWhere('url', 'like', '%://' . $host . '%')
            ->orWhere('url', 'like', '%://www.' . $host . '%')
            ->exists();
    }

    private function normalizeUrl(string $url): ?string
    {
        $url = $this->firstValue($url);
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
}
