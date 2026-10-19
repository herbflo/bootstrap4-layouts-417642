<?php
/*
 * Applies the bulk-create fix to LinkController::bulk().
 *
 *   cd ~/domains/linkdirectory.pro/lapp
 *   /opt/alt/php81/usr/bin/php apply-bulk-fix.php
 *
 * Backs up the controller, replaces only the loop in bulk(), checks the
 * result for syntax errors and restores the backup if the check fails.
 * Safe to run twice: it does nothing when the fix is already in place.
 */

$file = __DIR__ . '/app/Http/Controllers/LinkController.php';
if (isset($argv[1])) {
    $file = $argv[1];
}

$start = '            foreach ($explode_lines as $line) {';
$end = "            \$failed = '';";

$src = file_get_contents($file);
if ($src === false) {
    exit("Cannot read $file\n");
}
if (strpos($src, '// Skip empty lines (e.g. the trailing newline') !== false) {
    exit("Already patched, nothing to do.\n");
}
if (substr_count($src, $start) !== 1 || substr_count($src, $end) !== 1) {
    exit("Could not find the original bulk() loop exactly once. Not patching.\n");
}

$a = strpos($src, $start);
$b = strpos($src, $end);
if ($b <= $a) {
    exit("Unexpected layout in bulk(). Not patching.\n");
}

$newLoop = <<<'LOOP'
            foreach ($explode_lines as $line) {

                // Skip empty lines (e.g. the trailing newline in the textarea)
                if (trim($line) === '') {
                    continue;
                }

                $explode_line = array_map('trim', explode("|", $line));

                $title = $explode_line[0] ?? null;
                $url = $explode_line[1] ?? null;
                $category = $explode_line[2] ?? null;
                $description = $explode_line[3] ?? null;

                $cat_language = $category != null ? Category::where('id', $category)->first() : null;

                if ($title != null && $url != null && $cat_language != null && $description != null) {

                    $slug = SlugService::createSlug(Link::class, 'slug', $title);

                    $last_id = Link::insertGetId(array(
                        'title' => $title,
                        'description' => $description,
                        'slug' => $slug,
                        'url' => $url,
                        'bypass_redirect' => 0,
                        'bypass_details' => 0,
                        'page_views' => 0,
                        'clicks' => 0,
                        'featured_category' => 0,
                        'featured_home' => 0,
                        'votes' => '0.00',
                        'total_votes' => 0,
                        'badge' => 0,
                        'owner' => 1,
                        'package' => 0,
                        'gallery' => '',
                        'language' => $cat_language->language,
                        'enabled' => 1,
                        'status' => 2,
                        'is_subscription' => 0,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ));

                    // Insert Category Record
                    DB::table('category_link')->insert(
                        [
                            'link_id' => $last_id,
                            'category_id' => $cat_language->id,
                        ]
                    );

                    $s++;
                } else {

                    array_push($failed_links, $line);

                    $f++;

                }

            }
LOOP;

$backup = $file . '.bak-' . date('Ymd-His');
if (!copy($file, $backup)) {
    exit("Cannot write backup. Not patching.\n");
}

file_put_contents($file, substr($src, 0, $a) . $newLoop . "\n\n" . substr($src, $b));

exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
if ($code !== 0) {
    copy($backup, $file);
    exit("Syntax check failed, original restored:\n" . implode("\n", $out) . "\n");
}

echo "Patched OK. Backup: $backup\n";
