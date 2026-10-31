<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('watchlist', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('media_id')->index();
            $t->timestamps();
            $t->unique(['user_id', 'media_id']);
        });
        Schema::create('release_reports', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('release_id')->index();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('reason', 20);           // broken | password | wrong | spam | other
            $t->string('note', 500)->nullable();
            $t->string('status', 10)->default('open')->index(); // open | done
            $t->timestamps();
            $t->unique(['release_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_reports');
        Schema::dropIfExists('watchlist');
    }
};
