<?php
namespace Tests\Feature;

use App\Models\Blacklist;
use App\Models\Newsgroup;
use App\Models\Release;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReleasesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $a = User::factory()->create();
        $a->forceFill(['is_admin' => true])->save();
        return $a;
    }

    private function rel(array $o = []): Release
    {
        $g = Newsgroup::firstOrCreate(['name' => 'a.b.test']);
        return Release::create(array_merge([
            'guid' => sha1(uniqid('', true)), 'newsgroup_id' => $g->id, 'name' => 'x' . uniqid(), 'poster' => 'p@x',
            'size' => 5_000_000, 'completion' => 100, 'category_id' => 7000, 'obfuscated' => true,
            'files' => ['a.rar' => ['total' => 1, 'segs' => [1 => ['id' => 'i@x', 'bytes' => 1]]]], 'posted_at' => now(),
        ], $o));
    }

    public function test_page_with_search_and_checkboxes(): void
    {
        $this->rel(['name' => 'Gesucht.2024', 'poster' => 'a@x']);
        $this->rel(['name' => 'Anderes', 'poster' => 'spam@example.net']);
        $this->actingAs($this->admin())->get('/admin/releases?status=obfuscated&q=example.net')->assertOk()
            ->assertSee('Anderes')->assertDontSee('Gesucht.2024')->assertSee('name="ids[]"', false);
    }

    public function test_bulk_delete_selected(): void
    {
        $a = $this->rel(); $b = $this->rel(); $c = $this->rel();
        $this->actingAs($this->admin())->post('/admin/releases/bulk', ['action' => 'delete', 'scope' => 'selected', 'ids' => [$a->id, $b->id]])
            ->assertRedirect()->assertSessionHas('status', '2 Releases gelöscht.');
        $this->assertSame([$c->id], Release::pluck('id')->all());
    }

    public function test_bulk_all_matches_filter_only(): void
    {
        $this->rel(['poster' => 'spam@example.net']);
        $this->rel(['poster' => 'spam@example.net']);
        $keep = $this->rel(['poster' => 'gut@x']);
        $visible = $this->rel(['obfuscated' => false, 'poster' => 'spam@example.net']);
        $this->actingAs($this->admin())->post('/admin/releases/bulk', ['action' => 'delete', 'scope' => 'all', 'status' => 'obfuscated', 'q' => 'example.net'])
            ->assertSessionHas('status', '2 Releases gelöscht.');
        $this->assertNotNull($keep->fresh());
        $this->assertNotNull($visible->fresh(), 'nicht verschleiert = nicht im Filter');
    }

    public function test_bulk_ok_and_single_row_buttons(): void
    {
        $a = $this->rel(); $b = $this->rel();
        $this->actingAs($this->admin())->post('/admin/releases/bulk', ['action' => 'ok', 'scope' => 'selected', 'ids' => [$a->id]]);
        $this->assertTrue($a->fresh()->locked);
        $this->assertFalse($a->fresh()->obfuscated);

        $this->actingAs($this->admin())->post('/admin/releases/bulk', ['row' => 'spam:' . $b->id, 'action' => 'delete', 'scope' => 'all']);
        $this->assertSame('spam', $b->fresh()->status, 'Zeilen-Knopf hat Vorrang vor der Auswahl oben');
    }

    public function test_blacklist_poster_marks_all_their_releases(): void
    {
        $a = $this->rel(['poster' => 'spam@example.net']);
        $other = $this->rel(['poster' => 'spam@example.net', 'obfuscated' => false]);
        $kept = $this->rel(['poster' => 'spam@example.net', 'status' => 'ok', 'locked' => true, 'obfuscated' => false]);
        $foreign = $this->rel(['poster' => 'gut@x']);

        $this->actingAs($this->admin())->post('/admin/releases/bulk', ['action' => 'blacklist', 'scope' => 'selected', 'ids' => [$a->id]])
            ->assertSessionHas('status');
        $this->assertTrue(Blacklist::where('type', 'poster')->where('pattern', 'spam@example.net')->exists());
        $this->assertSame('spam', $a->fresh()->status);
        $this->assertSame('spam', $other->fresh()->status);
        $this->assertSame('ok', $kept->fresh()->status, 'von Hand freigegebene bleiben');
        $this->assertSame('ok', $foreign->fresh()->status);
    }

    public function test_requires_selection(): void
    {
        $this->actingAs($this->admin())->post('/admin/releases/bulk', ['action' => 'delete', 'scope' => 'selected'])->assertSessionHasErrors('ids');
    }

    public function test_only_admins(): void
    {
        $r = $this->rel();
        $this->actingAs(User::factory()->create())->post('/admin/releases/bulk', ['action' => 'delete', 'scope' => 'selected', 'ids' => [$r->id]])->assertForbidden();
        $this->assertNotNull($r->fresh());
    }
}
