<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('releases', function (Blueprint $t) {
            $t->unsignedBigInteger('real_size')->nullable();   // aus PAR2: echte Größe der Nutzdateien
            $t->unsignedInteger('real_files')->nullable();     // aus PAR2: Anzahl Nutzdateien
        });
        Schema::table('predb', fn (Blueprint $t) => $t->index(['size']));
    }

    public function down(): void
    {
        Schema::table('releases', fn (Blueprint $t) => $t->dropColumn(['real_size', 'real_files']));
        Schema::table('predb', fn (Blueprint $t) => $t->dropIndex(['size']));
    }
};
