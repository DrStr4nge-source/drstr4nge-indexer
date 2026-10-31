<?php
namespace Tests\Feature;

use App\Models\Media;
use App\Models\Newsgroup;
use App\Models\Release;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MediaListTest extends TestCase
{
    use RefreshDatabase;

    private function rel(Media $m, array $o = []): Release
    {
        $g = Newsgroup::firstOrCreate(['name' => 'a.b.test']);
        return Release::create(array_merge([
            'guid' => sha1(uniqid('', true)), 'newsgroup_id' => $g->id, 'media_id' => $m->id, 'name' => 'Rel.' . uniqid(), 'poster' => 'p',
            'size' => 1_800_000_000, 'completion' => 100, 'category_id' => $m->type === 'tv' ? 5040 : 2040,
            'files' => ['a.rar' => ['total' => 1, 'segs' => [1 => ['id' => 'x@y', 'bytes' => 1]]]], 'posted_at' => now(),
        ], $o));
    }

    public function test_movies_page_shows_cards_with_details_and_releases(): void
    {
        $user = User::factory()->create();
        $m = Media::create(['type' => 'movie', 'source' => 'tmdb', 'source_id' => 1, 'tmdb_id' => 1, 'imdb_id' => 'tt0000001',
            'title' => 'Der König von Berlin', 'year' => 2017, 'rating' => 6.4, 'overview' => 'Kommissar zieht nach Berlin.',
            'genres' => 'Krimi, Komödie', 'director' => 'Lars Kraume', 'actors' => 'Florian Lukas, Anna Fischer']);
        $this->rel($m, ['name' => 'Der.Koenig.von.Berlin.2017.GERMAN.1080p.WEB.h264']);
        foreach (range(1, 5) as $i) $this->rel($m, ['posted_at' => now()->subDays($i)]);
        $show = Media::create(['type' => 'tv', 'source' => 'tmdb', 'source_id' => 2, 'title' => 'Nur Serie']);
        $this->rel($show);
        $hidden = Media::create(['type' => 'movie', 'source' => 'tmdb', 'source_id' => 3, 'title' => 'Nur Spam']);
        $this->rel($hidden, ['status' => 'spam']);

        $this->actingAs($user)->get('/filme')->assertOk()
            ->assertSee('Der König von Berlin')->assertSee('Krimi, Komödie')->assertSee('Lars Kraume')->assertSee('Florian Lukas')
            ->assertSee('Der.Koenig.von.Berlin.2017.GERMAN.1080p.WEB.h264')->assertSee('+ 2 weitere Releases')
            ->assertSee('https://www.imdb.com/title/tt0000001/', false)
            ->assertDontSee('Nur Serie')->assertDontSee('Nur Spam');

        $this->actingAs($user)->get('/serien')->assertOk()->assertSee('Nur Serie')->assertSee('Erfinder', false)->assertDontSee('Der König von Berlin');
        $this->actingAs($user)->get('/filme?genre=Krimi')->assertSee('Der König von Berlin');
        $this->actingAs($user)->get('/filme?q=gibtsnicht')->assertSee('Nichts gefunden');
    }

    public function test_empty_state(): void
    {
        $this->actingAs(User::factory()->create())->get('/filme')->assertOk()->assertSee('Noch keine Filme');
    }

    public function test_metadata_command_backfills_details_for_known_titles(): void
    {
        config(['indexer.tmdb.key' => 'k']);
        $m = Media::create(['type' => 'movie', 'source' => 'tmdb', 'source_id' => 603, 'tmdb_id' => 603, 'title' => 'The Matrix', 'year' => 1999]);
        Http::fake(['api.themoviedb.org/3/movie/603*' => Http::response([
            'id' => 603, 'genres' => [['name' => 'Action']], 'overview' => 'Neo.',
            'credits' => ['crew' => [['job' => 'Director', 'name' => 'Lana Wachowski']], 'cast' => [['name' => 'Keanu Reeves']]],
            'external_ids' => ['imdb_id' => 'tt0133093'],
        ])]);
        $this->artisan('indexer:metadata')->assertSuccessful();
        $m->refresh();
        $this->assertSame('Action', $m->genres);
        $this->assertSame('Lana Wachowski', $m->director);
        $this->assertSame('Keanu Reeves', $m->actors);
        $this->assertNotNull($m->details_at);

        // zweiter Lauf fragt nicht erneut
        Http::fake(fn () => throw new \RuntimeException('darf nicht aufgerufen werden'));
        $this->artisan('indexer:metadata')->assertSuccessful();
    }
}
