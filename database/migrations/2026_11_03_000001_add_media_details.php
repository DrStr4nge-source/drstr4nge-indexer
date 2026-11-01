<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('media', function (Blueprint $t) {
            $t->string('genres')->nullable();
            $t->string('director')->nullable();   // Film: Regie, Serie: Erfinder
            $t->text('actors')->nullable();
            $t->timestamp('details_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $t) {
            $t->dropIndex(['details_at']);
            $t->dropColumn(['genres', 'director', 'actors', 'details_at']);
        });
    }
};
