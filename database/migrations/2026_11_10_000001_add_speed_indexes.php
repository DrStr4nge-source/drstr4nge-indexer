<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Indizes für "sichtbare Releases, neueste zuerst" (Startseite, Suche, Kategorien, Titel). Dauert bei vielen Releases einige Minuten. */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('releases', function (Blueprint $t) {
            $t->index(['status', 'obfuscated', 'posted_at'], 'rel_visible_posted');
            $t->index(['status', 'obfuscated', 'category_id', 'posted_at'], 'rel_visible_cat');
            $t->index(['media_id', 'status', 'obfuscated', 'posted_at'], 'rel_media_visible');
            $t->index(['updated_at'], 'rel_updated');   // Admin-Liste, Überwachung
        });
    }

    public function down(): void
    {
        Schema::table('releases', function (Blueprint $t) {
            $t->dropIndex('rel_visible_posted');
            $t->dropIndex('rel_visible_cat');
            $t->dropIndex('rel_media_visible');
            $t->dropIndex('rel_updated');
        });
    }
};
