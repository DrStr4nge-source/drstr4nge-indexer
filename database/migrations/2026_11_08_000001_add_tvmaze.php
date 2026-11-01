<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('media', function (Blueprint $t) {
            $t->unsignedInteger('tvmaze_id')->nullable()->index();
            $t->timestamp('tvmaze_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $t) {
            $t->dropIndex(['tvmaze_id']);
            $t->dropColumn(['tvmaze_id', 'tvmaze_checked_at']);
        });
    }
};
