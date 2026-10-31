<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('media', function (Blueprint $t) {
            $t->id();
            $t->string('type', 8); // movie | tv
            $t->unsignedInteger('tmdb_id');
            $t->string('imdb_id', 16)->nullable()->index();
            $t->unsignedInteger('tvdb_id')->nullable()->index();
            $t->string('title');
            $t->unsignedSmallInteger('year')->nullable();
            $t->text('overview')->nullable();
            $t->decimal('rating', 3, 1)->nullable(); // TMDB-Bewertung (nicht IMDb)
            $t->string('poster_path')->nullable();   // Pfad bei TMDB
            $t->string('cover')->nullable();         // lokale WebP-Datei im public-Disk
            $t->timestamps();
            $t->unique(['type', 'tmdb_id']);
        });

        Schema::table('releases', function (Blueprint $t) {
            $t->unsignedBigInteger('media_id')->nullable()->index();
            $t->boolean('meta_checked')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('releases', fn (Blueprint $t) => $t->dropColumn(['media_id', 'meta_checked']));
        Schema::dropIfExists('media');
    }
};
