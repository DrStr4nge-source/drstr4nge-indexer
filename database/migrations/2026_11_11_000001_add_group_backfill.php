<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('newsgroups', function (Blueprint $t) {
            $t->unsignedSmallInteger('backfill_days')->nullable(); // leer = INDEXER_BACKFILL_DAYS
        });
    }

    public function down(): void
    {
        Schema::table('newsgroups', fn (Blueprint $t) => $t->dropColumn('backfill_days'));
    }
};
