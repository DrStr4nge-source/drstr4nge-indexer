<?php
namespace Tests\Feature;

use App\Models\Invite;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
    }

    private function form(array $o = []): array
    {
        return array_merge(['name' => 'neu', 'email' => 'Neu@Example.com', 'password' => 'geheim-Passwort-123', 'password_confirmation' => 'geheim-Passwort-123'], $o);
    }

    public function test_open_registration_creates_user_and_logs_in(): void
    {
        $this->get('/login')->assertOk()->assertSee('Registrieren');
        $this->get('/register')->assertOk()->assertSee('Konto erstellen')->assertDontSee('Einladungscode');
        $this->post('/register', $this->form())->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $u = User::where('email', 'neu@example.com')->first();
        $this->assertNotNull($u);
        $this->assertNotNull($u->api_key);
        $this->assertFalse($u->is_admin);
    }

    public function test_duplicate_email_and_password_mismatch(): void
    {
        User::factory()->create(['email' => 'neu@example.com']);
        $this->post('/register', $this->form())->assertSessionHasErrors('email');
        $this->post('/register', $this->form(['email' => 'x@example.com', 'password_confirmation' => 'anders']))->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_honeypot_blocks_bots(): void
    {
        $this->post('/register', $this->form(['website' => 'http://spam']))->assertRedirect(route('register'));
        $this->assertSame(0, User::count());
    }

    public function test_closed_registration(): void
    {
        Setting::put('registration_mode', 'closed');
        $this->get('/login')->assertDontSee('Noch kein Konto');
        $this->get('/register')->assertOk()->assertSee('geschlossen');
        $this->post('/register', $this->form())->assertForbidden();
        $this->assertSame(0, User::count());
    }

    public function test_invite_only_registration(): void
    {
        Setting::put('registration_mode', 'invite');
        $invite = Invite::create(['code' => 'ABCD-EFGH-JKLM']);

        $this->post('/register', $this->form())->assertSessionHasErrors('invite');
        $this->post('/register', $this->form(['invite' => 'FALS-CHER-CODE']))->assertSessionHasErrors('invite');
        $this->assertSame(0, User::count());

        $this->get('/register?code=abcd-efgh-jklm')->assertSee('ABCD-EFGH-JKLM');
        // Code klein und ohne Bindestriche geht auch
        $this->post('/register', $this->form(['invite' => 'abcdefghjklm']))->assertRedirect(route('dashboard'));
        $this->assertNotNull($invite->fresh()->used_by);

        auth()->logout();
        $this->post('/register', $this->form(['email' => 'zweiter@example.com', 'invite' => 'ABCD-EFGH-JKLM']))->assertSessionHasErrors('invite');
    }

    public function test_admin_manages_invites_and_mode(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->post('/admin/einladungen', ['count' => 3, 'note' => 'Freunde'])->assertSessionHas('status');
        $this->assertSame(3, Invite::count());
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}$/', Invite::first()->code);
        $this->actingAs($admin)->get('/admin/einladungen')->assertOk()->assertSee('Freunde')->assertSee('Link kopieren');
        $this->actingAs($admin)->delete(route('admin.invites.destroy', Invite::first()));
        $this->assertSame(2, Invite::count());

        $this->actingAs($admin)->post('/admin/einstellungen', ['site_name' => 'X', 'registration_mode' => 'invite']);
        Setting::flush();
        $this->assertSame('invite', \App\Support\Registration::mode());

        $this->actingAs(User::factory()->create())->get('/admin/einladungen')->assertForbidden();
    }

    public function test_registration_is_rate_limited(): void
    {
        foreach (range(1, 5) as $i) {
            $this->post('/register', $this->form(['email' => "u$i@example.com", 'password_confirmation' => 'falsch']));
        }
        $this->post('/register', $this->form(['email' => 'u6@example.com']))->assertStatus(429);
    }
}
