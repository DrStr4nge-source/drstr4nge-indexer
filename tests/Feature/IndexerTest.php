<?php
namespace Tests\Feature;

use App\Models\Download;
use App\Models\Newsgroup;
use App\Models\Payment;
use App\Models\Release;
use App\Models\User;
use App\Services\ReleaseBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class IndexerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('api:1');
    }

    private function release(array $o = []): Release
    {
        $group = Newsgroup::firstOrCreate(['name' => 'alt.test']);
        return Release::create(array_merge([
            'guid' => sha1(uniqid('', true)),
            'newsgroup_id' => $group->id,
            'name' => 'Show.S01E01.1080p',
            'poster' => 'poster@test',
            'size' => 5_000_000,
            'completion' => 100,
            'category_id' => 5040,
            'files' => ['a.rar' => ['total' => 1, 'segs' => [1 => ['id' => 'x@y', 'bytes' => 100]]]],
            'posted_at' => now(),
        ], $o));
    }

    private function row(string $subject, string $id, int $bytes = 700000): array
    {
        return [1, $subject, 'poster@x', 'Thu, 29 Oct 2026 12:00:00 +0000', "<$id>", '', $bytes, 100];
    }

    public function test_builder_merges_batches_and_classifies(): void
    {
        $g = Newsgroup::create(['name' => 'alt.test']);
        $b = app(ReleaseBuilder::class);
        $b->ingest($g, [$this->row('Cool.Show.S01E01.720p "c.rar" yEnc (1/4)', 'a1'), $this->row('Cool.Show.S01E01.720p "c.rar" yEnc (2/4)', 'a2')]);
        $this->assertSame(50, Release::first()->completion);
        $this->assertSame(0, Release::visible()->count());

        $b->ingest($g, [$this->row('Cool.Show.S01E01.720p "c.rar" yEnc (3/4)', 'a3'), $this->row('Cool.Show.S01E01.720p "c.rar" yEnc (4/4)', 'a4')]);
        $this->assertSame(1, Release::count());
        $r = Release::first();
        $this->assertSame(100, $r->completion);
        $this->assertSame(5040, $r->category_id);
        $this->assertSame(1, Release::visible()->count());
    }

    public function test_obfuscated_release_is_hidden(): void
    {
        $g = Newsgroup::create(['name' => 'alt.test']);
        app(ReleaseBuilder::class)->ingest($g, [
            $this->row('[1/2] - "ab12cd34ef56ab78cd90ef12" yEnc (1/1)', 'o1'),
            $this->row('[2/2] - "99aa88bb77cc66dd55ee44ff" yEnc (1/1)', 'o2'),
        ]);
        $this->assertSame(1, Release::count());
        $this->assertTrue(Release::first()->obfuscated);
        $this->assertSame(0, Release::visible()->count());
    }

    public function test_caps_needs_no_key_and_lists_categories(): void
    {
        $this->get('/api?t=caps')->assertOk()->assertSee('<category id="5000"', false);
    }

    public function test_search_rejects_bad_key(): void
    {
        $this->get('/api?t=search&apikey=nope')->assertStatus(401);
    }

    public function test_search_filters_by_category_and_hides_flagged(): void
    {
        $user = User::factory()->create();
        $this->release(['name' => 'Show.S01E01.1080p', 'category_id' => 5040]);
        $this->release(['name' => 'Movie.2020.1080p.BluRay', 'category_id' => 2040]);
        $this->release(['name' => 'Spam.Show.S01E01', 'category_id' => 5040, 'status' => 'spam']);

        $res = $this->get('/api?t=search&cat=5000&apikey=' . $user->api_key)->assertOk();
        $res->assertSee('Show.S01E01.1080p')->assertDontSee('Movie.2020')->assertDontSee('Spam.Show');
    }

    public function test_tvsearch_season_episode(): void
    {
        $user = User::factory()->create();
        $this->release(['name' => 'Cool.Show.S02E05.1080p']);
        $this->release(['name' => 'Cool.Show.S02E06.1080p']);
        $this->get('/api?t=tvsearch&q=Cool.Show&season=2&ep=5&apikey=' . $user->api_key)
            ->assertSee('S02E05')->assertDontSee('S02E06');
    }

    public function test_get_returns_nzb_and_counts_download(): void
    {
        $user = User::factory()->create();
        $r = $this->release();
        $res = $this->get('/api?t=get&id=' . $r->guid . '&apikey=' . $user->api_key)->assertOk();
        $this->assertStringContainsString('application/x-nzb', $res->headers->get('Content-Type'));
        $this->assertSame(1, Download::count());
    }

    public function test_free_users_hit_daily_limit_vip_does_not(): void
    {
        config(['indexer.free_grabs_per_day' => 1]);
        $user = User::factory()->create();
        $r = $this->release();
        $this->actingAs($user)->get(route('download', $r->guid))->assertOk();
        $this->actingAs($user)->get(route('download', $r->guid))->assertStatus(429);

        $user->forceFill(['vip_until' => now()->addMonth()])->save();
        $this->actingAs($user)->get(route('download', $r->guid))->assertOk();
    }

    public function test_api_rate_limit(): void
    {
        config(['indexer.api_limit' => 2]);
        $user = User::factory()->create();
        RateLimiter::clear('api:' . $user->id);
        $url = '/api?t=search&apikey=' . $user->api_key;
        $this->get($url)->assertOk();
        $this->get($url)->assertOk();
        $this->get($url)->assertStatus(429);
    }

    public function test_webhook_extends_vip_once_and_checks_signature(): void
    {
        config(['indexer.btcpay.webhook_secret' => 'topsecret']);
        $user = User::factory()->create();
        Payment::create(['user_id' => $user->id, 'invoice_id' => 'INV1', 'plan' => '1m', 'amount' => 5, 'currency' => 'EUR']);

        $body = json_encode(['type' => 'InvoiceSettled', 'invoiceId' => 'INV1']);
        $sig = 'sha256=' . hash_hmac('sha256', $body, 'topsecret');
        $call = fn ($s) => $this->call('POST', '/btcpay/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_BTCPAY_SIG' => $s], $body);

        $call('sha256=wrong')->assertStatus(403);
        $this->assertFalse($user->fresh()->isVip());

        $call($sig)->assertOk();
        $vip = $user->fresh()->vip_until;
        $this->assertTrue($user->fresh()->isVip());

        $call($sig)->assertOk(); // doppelt gesendet: keine zweite Verlängerung
        $this->assertTrue($vip->equalTo($user->fresh()->vip_until));
    }

    public function test_admin_area_is_protected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin')->assertForbidden();
        $user->forceFill(['is_admin' => true])->save();
        $this->actingAs($user)->get('/admin')->assertOk();
        $this->actingAs($user)->get('/admin/releases')->assertOk();
        $this->actingAs($user)->get('/admin/blacklist')->assertOk();
    }

    public function test_admin_can_release_flagged_and_blacklist_marks_spam(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $r = $this->release(['status' => 'spam']);
        $this->actingAs($admin)->post(route('admin.releases.bulk'), ['row' => 'ok:' . $r->id])->assertRedirect();
        $this->assertSame('ok', $r->fresh()->status);
        $this->assertTrue($r->fresh()->locked);

        $this->actingAs($admin)->post(route('admin.blacklist.store'), ['type' => 'poster', 'pattern' => 'bad*@*'])->assertRedirect();
        $g = Newsgroup::first();
        app(ReleaseBuilder::class)->ingest($g, [['1', 'Some.Show.S01E01 "s.rar" yEnc (1/1)', 'bad1@spam.net', 'Thu, 29 Oct 2026 12:00:00 +0000', '<b1>', '', 5_000_000, 1]]);
        $this->assertSame('spam', Release::where('poster', 'bad1@spam.net')->first()->status);

        $this->actingAs($admin)->post(route('admin.blacklist.store'), ['type' => 'name_regex', 'pattern' => '(unclosed'])->assertSessionHasErrors('pattern');
    }

    public function test_cleanup_removes_old_and_incomplete_but_keeps_fresh(): void
    {
        config(['indexer.retention_days' => 100]);
        $old = $this->release(['posted_at' => now()->subDays(200)]);
        $incomplete = $this->release(['completion' => 40]);
        Release::where('id', $incomplete->id)->update(['updated_at' => now()->subDays(5)]);
        $fresh = $this->release();

        $this->artisan('indexer:cleanup')->assertSuccessful();
        $this->assertNull(Release::find($old->id));
        $this->assertNull(Release::find($incomplete->id));
        $this->assertNotNull(Release::find($fresh->id));
    }

    public function test_cleanup_deletes_old_obfuscated_and_passworded(): void
    {
        config(['indexer.delete_obfuscated_days' => 3, 'indexer.delete_passworded_days' => 1]);
        $oldObf = $this->release(['obfuscated' => true]);
        $newObf = $this->release(['obfuscated' => true]);
        $lockedObf = $this->release(['obfuscated' => true, 'locked' => true]);
        $oldPw = $this->release(['status' => 'passworded']);
        $newPw = $this->release(['status' => 'passworded']);
        $normal = $this->release();
        Release::whereIn('id', [$oldObf->id, $lockedObf->id, $normal->id])->update(['created_at' => now()->subDays(4)]);
        Release::where('id', $oldPw->id)->update(['created_at' => now()->subDays(2)]);

        $this->artisan('indexer:cleanup')->assertSuccessful();
        $this->assertNull(Release::find($oldObf->id));
        $this->assertNull(Release::find($oldPw->id));
        foreach ([$newObf, $lockedObf, $newPw, $normal] as $r) {
            $this->assertNotNull(Release::find($r->id));
        }

        // 0 = abgeschaltet
        config(['indexer.delete_obfuscated_days' => 0]);
        Release::where('id', $newObf->id)->update(['created_at' => now()->subDays(30)]);
        $this->artisan('indexer:cleanup')->assertSuccessful();
        $this->assertNotNull(Release::find($newObf->id));
    }
}
