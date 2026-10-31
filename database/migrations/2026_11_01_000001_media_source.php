<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Macht die Metadaten-Quelle austauschbar: Identität = (type, source, source_id), tmdb_id wird optional. */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('media', function (Blueprint $t) {
            $t->string('source', 16)->default('tmdb');
            $t->unsignedInteger('source_id')->nullable();
        });

        // Bestehende TMDB-Zeilen übernehmen; poster_path enthält jetzt die volle Bild-URL
        DB::table('media')->orderBy('id')->each(function ($m) {
            $poster = $m->poster_path;
            if ($poster && !str_starts_with($poster, 'http')) {
                $poster = 'https://image.tmdb.org/t/p/w500' . $poster;
            }
            DB::table('media')->where('id', $m->id)->update(['source_id' => $m->tmdb_id, 'poster_path' => $poster]);
        });

        Schema::table('media', function (Blueprint $t) {
            $t->dropUnique(['type', 'tmdb_id']);
            $t->unsignedInteger('tmdb_id')->nullable()->change();
            $t->unique(['type', 'source', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $t) {
            $t->dropUnique(['type', 'source', 'source_id']);
        });
        DB::table('media')->whereNull('tmdb_id')->delete(); // Zeilen anderer Quellen haben keine TMDB-ID
        Schema::table('media', function (Blueprint $t) {
            $t->unsignedInteger('tmdb_id')->nullable(false)->change();
            $t->unique(['type', 'tmdb_id']);
            $t->dropColumn(['source', 'source_id']);
        });
    }
};
