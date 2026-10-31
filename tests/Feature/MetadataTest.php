<?php
namespace Tests\Feature;

use App\Models\Media;
use App\Models\Newsgroup;
use App\Models\Release;
use App\Models\User;
use App\Services\CoverImage;
use App\Services\MetadataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MetadataTest extends TestCase
{
    use RefreshDatabase;

    private function release(string $name, int $cat, ?int $mediaId = null): Release
    {
        $g = Newsgroup::firstOrCreate(['name' => 'alt.test']);
        return Release::create([
            'guid' => sha1(uniqid('', true)), 'newsgroup_id' => $g->id, 'name' => $name, 'poster' => 'p',
            'size' => 5_000_000, 'completion' => 100, 'category_id' => $cat, 'media_id' => $mediaId,
            'files' => ['a.rar' => ['total' => 1, 'segs' => [1 => ['id' => 'x@y', 'bytes' => 100]]]], 'posted_at' => now(),
        ]);
    }

    public function test_id_search_and_attributes(): void
    {
        $user = User::factory()->create();
        $m = Media::create(['type' => 'movie', 'tmdb_id' => 603, 'imdb_id' => 'tt0133093', 'title' => 'The Matrix', 'year' => 1999, 'cover' => 'covers/movie-603.webp']);
        $this->release('The.Matrix.1999.1080p.BluRay', 2040, $m->id);
        $this->release('Other.Movie.2001.1080p.BluRay', 2040);

        foreach (['tt0133093', '0133093', '133093'] as $id) {
            $this->get("/api?t=movie&imdbid=$id&apikey={$user->api_key}")
                ->assertOk()->assertSee('The.Matrix.1999')->assertDontSee('Other.Movie');
        }
        $this->get("/api?t=movie&tmdbid=603&apikey={$user->api_key}")->assertSee('The.Matrix.1999')->assertDontSee('Other.Movie');
        $this->get("/api?t=movie&tmdbid=999&apikey={$user->api_key}")->assertDontSee('The.Matrix');

        $this->get("/api?t=movie&imdbid=tt0133093&apikey={$user->api_key}")
            ->assertSee('name="imdb" value="0133093"', false)->assertSee('name="tmdbid" value="603"', false)->assertSee('coverurl', false);
    }

    public function test_tvdb_search(): void
    {
        $user = User::factory()->create();
        $m = Media::create(['type' => 'tv', 'tmdb_id' => 1399, 'tvdb_id' => 121361, 'title' => 'Game of Thrones']);
        $this->release('Game.of.Thrones.S01E01.1080p', 5040, $m->id);
        $this->release('Other.Show.S01E01.1080p', 5040);
        $this->get("/api?t=tvsearch&tvdbid=121361&season=1&ep=1&apikey={$user->api_key}")
            ->assertSee('Game.of.Thrones')->assertDontSee('Other.Show');
    }

    public function test_metadata_command_links_release_and_stores_webp_cover(): void
    {
        if (!CoverImage::supported()) {
            $this->markTestSkipped('GD ohne WebP');
        }
        config(['indexer.tmdb.key' => 'testkey']);
        Storage::fake('public');

        $im = imagecreatetruecolor(500, 750);
        ob_start();
        imagejpeg($im);
        $jpeg = ob_get_clean();

        Http::fake([
            'api.themoviedb.org/3/search/movie*' => Http::response(['results' => [[
                'id' => 603, 'title' => 'The Matrix', 'original_title' => 'The Matrix', 'release_date' => '1999-03-31',
                'poster_path' => '/p.jpg', 'overview' => 'Neo.', 'vote_average' => 8.2,
            ]]]),
            'api.themoviedb.org/3/movie/603*' => Http::response(['id' => 603, 'external_ids' => ['imdb_id' => 'tt0133093']]),
            'image.tmdb.org/*' => Http::response($jpeg, 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $a = $this->release('The.Matrix.1999.1080p.BluRay.x264-GRP', 2040);
        $b = $this->release('The.Matrix.1999.720p.BluRay.x264-OTHER', 2040); // gleicher Film: nur eine TMDB-Suche
        $c = $this->release('Unparseable-Name', 2040);

        $this->artisan('indexer:metadata')->assertSuccessful();

        $media = Media::first();
        $this->assertSame('tmdb', $media->source);
        $this->assertSame(603, $media->source_id);
        $this->assertSame('tt0133093', $media->imdb_id);
        $this->assertSame($media->id, $a->fresh()->media_id);
        $this->assertSame($media->id, $b->fresh()->media_id);
        $this->assertNull($c->fresh()->media_id);
        $this->assertTrue($c->fresh()->meta_checked);
        $this->assertSame(1, Media::count());

        Storage::disk('public')->assertExists('covers/tmdb-movie-603.webp');
        $this->assertSame('WEBP', substr(Storage::disk('public')->get('covers/tmdb-movie-603.webp'), 8, 4));
        Http::assertSentCount(3); // 1x Suche, 1x Details, 1x Poster
    }

    public function test_other_provider_can_be_plugged_in(): void
    {
        Storage::fake('public');
        config([
            'indexer.metadata.provider' => 'fake',
            'indexer.metadata.providers.fake' => FakeProvider::class,
        ]);
        $user = User::factory()->create();
        $r = $this->release('Some.Show.S01E01.1080p.WEB', 5040);

        $this->artisan('indexer:metadata')->assertSuccessful();

        $media = Media::first();
        $this->assertSame('fake', $media->source);
        $this->assertSame(7, $media->source_id);
        $this->assertNull($media->tmdb_id);
        $this->assertSame($media->id, $r->fresh()->media_id);

        // Ohne TMDB-ID erscheint kein tmdbid-Attribut, die IMDb-ID aber schon
        $this->get("/api?t=tvsearch&imdbid=tt7654321&apikey={$user->api_key}")
            ->assertSee('Some.Show.S01E01')->assertSee('name="imdb" value="7654321"', false)->assertDontSee('tmdbid', false);
    }

    public function test_provider_none_fails_cleanly(): void
    {
        config(['indexer.metadata.provider' => 'none']);
        $this->artisan('indexer:metadata')->assertFailed();
    }

    public function test_command_without_key_fails_cleanly(): void
    {
        config(['indexer.tmdb.key' => null]);
        $this->artisan('indexer:metadata')->assertFailed();
    }

    public function test_api_error_stops_without_marking_checked(): void
    {
        config(['indexer.tmdb.key' => 'testkey']);
        Http::fake(['api.themoviedb.org/*' => Http::response([], 429)]);
        $r = $this->release('The.Matrix.1999.1080p.BluRay.x264-GRP', 2040);
        $this->artisan('indexer:metadata')->assertSuccessful();
        $this->assertFalse($r->fresh()->meta_checked);
    }

    public function test_browse_shows_cover_and_links(): void
    {
        $user = User::factory()->create();
        $m = Media::create(['type' => 'movie', 'tmdb_id' => 603, 'imdb_id' => 'tt0133093', 'title' => 'The Matrix', 'year' => 1999, 'cover' => 'covers/movie-603.webp']);
        $this->release('The.Matrix.1999.1080p.BluRay', 2040, $m->id);
        $this->actingAs($user)->get('/browse')->assertOk()
            ->assertSee('covers/movie-603.webp', false)->assertSee('https://www.imdb.com/title/tt0133093/', false)->assertSee('TMDB');
    }
}

class FakeProvider implements MetadataProvider
{
    public function name(): string { return 'fake'; }
    public function enabled(): bool { return true; }
    public function find(array $parsed): ?array
    {
        return ['source' => 'fake', 'source_id' => 7, 'type' => $parsed['type'], 'title' => 'Some Show', 'year' => 2020, 'imdb_id' => 'tt7654321'];
    }
}
