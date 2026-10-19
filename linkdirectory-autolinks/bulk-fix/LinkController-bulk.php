<?php

/*
 * Replacement for the foreach loop in LinkController::bulk()
 * (lapp/app/Http/Controllers/LinkController.php).
 *
 * The original loop reads $explode_line[2] and $cat_language->language before
 * checking that the line is complete, so a blank line (the usual trailing
 * newline in the textarea), a line with fewer than 3 "|" fields, or a category
 * ID that doesn't exist throws an error and the whole request fails with a 500.
 * This version checks every line first and reports bad lines as failed instead.
 *
 * Replace everything from "foreach ($explode_lines as $line) {" down to the
 * closing brace of that loop (just before "$failed = '';") with this:
 */

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
