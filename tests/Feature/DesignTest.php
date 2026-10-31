<?php
namespace Tests\Feature;

use App\Models\Media;
use App\Models\Newsgroup;
use App\Models\Release;
use App\Models\Setting;
use App\Models\User;
use App\Support\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
    }

    private function release(array $o = []): Release
    {
        $g = Newsgroup::firstOrCreate(['name' => 'alt.test']);
        return Release::create(array_merge([
            'guid' => sha1(uniqid('', true)), 'newsgroup_id' => $g->id, 'name' => 'Some.Movie.2024.1080p', 'poster' => 'p',
            'size' => 5_000_000, 'completion' => 100, 'category_id' => 2040,
            'files' => ['a.rar' => ['total' => 1, 'segs' => [1 => ['id' => 'x@y', 'bytes' => 100]]]], 'posted_at' => now(),
        ], $o));
    }

    public function test_dashboard_shows_rows_with_covers_and_latest_releases(): void
    {
        $user = User::factory()->create();
        $movie = Media::create(['type' => 'movie', 'source' => 'tmdb', 'source_id' => 1, 'tmdb_id' => 1, 'title' => 'Der Film', 'year' => 2024, 'rating' => 7.8, 'cover' => 'covers/tmdb-movie-1.webp']);
        $show = Media::create(['type' => 'tv', 'source' => 'tmdb', 'source_id' => 2, 'tmdb_id' => 2, 'title' => 'Die Serie', 'year' => 2023]);
        $hidden = Media::create(['type' => 'movie', 'source' => 'tmdb', 'source_id' => 3, 'tmdb_id' => 3, 'title' => 'Nur Spam']);
        $this->release(['media_id' => $movie->id]);
        $this->release(['media_id' => $show->id, 'name' => 'Die.Serie.S01E01.1080p', 'category_id' => 5040]);
        $this->release(['media_id' => $hidden->id, 'status' => 'spam', 'name' => 'Nur.Spam.2024']);

        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('Der Film')->assertSee('7.8/10')->assertSee('covers/tmdb-movie-1.webp', false)
            ->assertSee('Die Serie')
            ->assertDontSee('Nur Spam')
            ->assertSee('Some.Movie.2024.1080p')
            ->assertSee('tm-scroller', false);
    }

    public function test_dashboard_without_metadata_shows_hint_for_admin_only(): void
    {
        $this->release();
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertDontSee('TMDB_API_KEY');
        $user->forceFill(['is_admin' => true])->save();
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('TMDB_API_KEY');
    }

    public function test_title_page(): void
    {
        $user = User::factory()->create();
        $m = Media::create(['type' => 'movie', 'source' => 'tmdb', 'source_id' => 9, 'tmdb_id' => 9, 'imdb_id' => 'tt0000009', 'title' => 'Mein Titel', 'year' => 2020, 'overview' => 'Worum es geht.']);
        $this->release(['media_id' => $m->id, 'name' => 'Mein.Titel.2020.1080p']);
        $this->release(['media_id' => $m->id, 'name' => 'Mein.Titel.2020.720p']);

        $this->actingAs($user)->get(route('title', $m))->assertOk()
            ->assertSee('Mein Titel')->assertSee('Worum es geht.')->assertSee('2 Releases')
            ->assertSee('https://www.imdb.com/title/tt0000009/', false)->assertSee('Mein.Titel.2020.720p');

        $empty = Media::create(['type' => 'movie', 'source' => 'tmdb', 'source_id' => 10, 'title' => 'Ohne Releases']);
        $this->actingAs($user)->get(route('title', $empty))->assertNotFound();
    }

    public function test_account_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/konto')->assertOk()->assertSee($user->api_key)->assertSee('Letzte Downloads');
    }

    public function test_all_pages_render_with_theme(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->release();
        foreach (['/dashboard', '/filme', '/serien', '/browse', '/browse?cat=2000&q=Movie', '/vip', '/konto', '/profile', '/admin', '/admin/users', '/admin/groups', '/admin/releases', '/admin/blacklist', '/admin/payments', '/admin/einstellungen'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk()->assertSee('tm-body', false)->assertSee('tm-nav', false);
        }
    }

    public function test_guest_pages_use_theme(): void
    {
        $this->get('/login')->assertOk()->assertSee('tm-guest', false);
        $this->get('/register')->assertOk()->assertSee('tm-guest', false);
    }

    public function test_settings_only_for_admins(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/einstellungen')->assertForbidden();
    }

    public function test_logo_upload_name_and_removal(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)->post('/admin/einstellungen', [
            'site_name' => 'Mein Index',
            'logo' => UploadedFile::fake()->image('logo.png', 300, 64),
        ])->assertRedirect(route('admin.settings'));

        Setting::flush();
        $path = Setting::get('logo_path');
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame('Mein Index', Brand::name());

        $this->actingAs($admin)->get('/dashboard')->assertSee($path, false)->assertSee('<title>Mein Index</title>', false);

        // Neues Logo ersetzt das alte, altes wird gelöscht
        $this->travel(2)->seconds();
        $this->actingAs($admin)->post('/admin/einstellungen', ['site_name' => 'Mein Index', 'logo' => UploadedFile::fake()->image('neu.webp', 200, 50)]);
        Setting::flush();
        Storage::disk('public')->assertMissing($path);
        $new = Setting::get('logo_path');
        Storage::disk('public')->assertExists($new);

        // Entfernen
        $this->actingAs($admin)->post('/admin/einstellungen', ['site_name' => 'Mein Index', 'remove_logo' => '1']);
        Setting::flush();
        $this->assertNull(Setting::get('logo_path'));
        Storage::disk('public')->assertMissing($new);
    }

    public function test_logo_rejects_svg_and_large_files(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->actingAs($admin)->post('/admin/einstellungen', ['site_name' => 'X', 'logo' => $svg])->assertSessionHasErrors('logo');

        $big = UploadedFile::fake()->image('big.png', 100, 100)->size(3000);
        $this->actingAs($admin)->post('/admin/einstellungen', ['site_name' => 'X', 'logo' => $big])->assertSessionHasErrors('logo');

        Setting::flush();
        $this->assertNull(Setting::get('logo_path'));
    }
}
