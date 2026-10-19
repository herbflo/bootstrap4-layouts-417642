<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the phone / e-mail / address columns used by links:auto-create,
 * only where the links table does not have them already.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = (new (config('autolinks.models.link')))->getTable();
        $cols = array_filter(config('autolinks.link_columns'));
        unset($cols['category'], $cols['country']);

        foreach ($cols as $col) {
            if (!Schema::hasColumn($table, $col)) {
                Schema::table($table, function (Blueprint $t) use ($col) {
                    $t->string($col)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        // Left in place: the columns may have existed before this migration.
    }
};
