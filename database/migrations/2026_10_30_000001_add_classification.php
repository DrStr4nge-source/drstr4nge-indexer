<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('releases', function (Blueprint $t) {
            $t->unsignedSmallInteger('category_id')->default(7000)->index();
            $t->string('status', 16)->default('ok')->index(); // ok | spam | passworded
            $t->boolean('obfuscated')->default(false);
            $t->boolean('inspected')->default(false)->index();
            $t->boolean('locked')->default(false);            // Admin hat Status von Hand gesetzt
            $t->unsignedSmallInteger('files_expected')->nullable();
        });

        Schema::create('blacklist_rules', function (Blueprint $t) {
            $t->id();
            $t->string('type', 16);     // poster | name_regex
            $t->string('pattern', 255); // poster darf * als Platzhalter enthalten
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blacklist_rules');
        Schema::table('releases', function (Blueprint $t) {
            $t->dropIndex(['category_id']);
            $t->dropIndex(['status']);
            $t->dropIndex(['inspected']);
            $t->dropColumn(['category_id', 'status', 'obfuscated', 'inspected', 'locked', 'files_expected']);
        });
    }
};
