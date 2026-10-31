<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('predb', function (Blueprint $t) {
            $t->id();
            $t->string('title', 255)->unique();
            $t->string('category', 50)->nullable();
            $t->string('source', 50)->nullable();
            $t->unsignedBigInteger('size')->nullable();
            $t->unsignedInteger('files')->nullable();
            $t->string('filename', 255)->nullable();
            $t->unsignedTinyInteger('nuked')->default(0);
            $t->string('nuke_reason', 255)->nullable();
            $t->timestamp('predate')->nullable()->index();
            $t->timestamps();
        });
        Schema::table('releases', function (Blueprint $t) {
            $t->foreignId('pre_id')->nullable()->after('name')->index();
            $t->timestamp('pre_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('releases', fn (Blueprint $t) => $t->dropColumn(['pre_id', 'pre_checked_at']));
        Schema::dropIfExists('predb');
    }
};
