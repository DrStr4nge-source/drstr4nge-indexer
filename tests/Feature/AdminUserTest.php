<?php
namespace Tests\Feature;

use App\Models\Newsgroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $a = User::factory()->create();
        $a->forceFill(['is_admin' => true])->save();
        return $a;
    }

    public function test_admin_can_give_vip_to_himself_and_others(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create();

        // Tage kommen aus dem Formular als Text
        $this->actingAs($admin)->post(route('admin.users.update', $admin), ['action' => 'add_vip', 'days' => '30'])->assertRedirect();
        $this->assertTrue($admin->fresh()->isVip());
        $this->assertEqualsWithDelta(30, now()->diffInDays($admin->fresh()->vip_until), 1);

        // Verlängern addiert auf die Restzeit
        $this->actingAs($admin)->post(route('admin.users.update', $admin), ['action' => 'add_vip', 'days' => '10']);
        $this->assertEqualsWithDelta(40, now()->diffInDays($admin->fresh()->vip_until), 1);

        // Ohne Tage-Angabe: 30
        $this->actingAs($admin)->post(route('admin.users.update', $other), ['action' => 'add_vip']);
        $this->assertEqualsWithDelta(30, now()->diffInDays($other->fresh()->vip_until), 1);

        $this->actingAs($admin)->post(route('admin.users.update', $other), ['action' => 'remove_vip']);
        $this->assertFalse($other->fresh()->isVip());
    }

    public function test_other_actions(): void
    {
        $admin = $this->admin();
        $u = User::factory()->create();
        foreach (['ban' => fn () => $u->fresh()->is_banned, 'make_admin' => fn () => $u->fresh()->is_admin] as $action => $check) {
            $this->actingAs($admin)->post(route('admin.users.update', $u), ['action' => $action, 'days' => '30'])->assertRedirect();
            $this->assertTrue($check());
        }
        $this->actingAs($admin)->post(route('admin.users.update', $admin), ['action' => 'remove_admin']);
        $this->assertTrue($admin->fresh()->is_admin, 'Admin kann sich nicht selbst entmachten');
    }

    public function test_invalid_days_are_rejected(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.users.update', $admin), ['action' => 'add_vip', 'days' => 'abc'])->assertSessionHasErrors('days');
        $this->actingAs($admin)->post(route('admin.users.update', $admin), ['action' => 'add_vip', 'days' => '0'])->assertSessionHasErrors('days');
    }

    public function test_admin_can_reset_group(): void
    {
        $g = Newsgroup::create(['name' => 'a.b.test', 'last_article' => 5000]);
        $this->actingAs($this->admin())->post(route('admin.groups.reset', $g))->assertRedirect()->assertSessionHas('status');
        $this->assertSame(0, (int) $g->fresh()->last_article);
    }
}
