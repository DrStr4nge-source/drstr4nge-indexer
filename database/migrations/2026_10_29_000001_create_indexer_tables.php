<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('api_key', 64)->nullable()->unique();
            $t->boolean('is_admin')->default(false);
            $t->boolean('is_banned')->default(false);
            $t->timestamp('vip_until')->nullable();
        });

        Schema::create('newsgroups', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->boolean('active')->default(true);
            $t->unsignedBigInteger('last_article')->default(0);
            $t->timestamps();
        });

        Schema::create('releases', function (Blueprint $t) {
            $t->id();
            $t->char('guid', 40)->unique();
            $t->foreignId('newsgroup_id')->constrained()->cascadeOnDelete();
            $t->string('name', 500)->index();
            $t->string('poster')->nullable();
            $t->unsignedBigInteger('size')->default(0);
            $t->unsignedTinyInteger('completion')->default(0);
            $t->longText('files'); // JSON: Dateien und Segmente
            $t->timestamp('posted_at')->nullable()->index();
            $t->timestamps();
        });

        Schema::create('downloads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('release_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->index(['user_id', 'created_at']);
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('invoice_id')->unique();
            $t->string('plan');
            $t->decimal('amount', 10, 2);
            $t->string('currency', 8);
            $t->string('status')->default('pending'); // pending | paid
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('downloads');
        Schema::dropIfExists('releases');
        Schema::dropIfExists('newsgroups');
        Schema::table('users', function (Blueprint $t) {
            $t->dropUnique(['api_key']); // SQLite: Index vor der Spalte entfernen
            $t->dropColumn(['api_key', 'is_admin', 'is_banned', 'vip_until']);
        });
    }
};
