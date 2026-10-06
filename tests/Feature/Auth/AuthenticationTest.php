<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPaystack();
    }

    public function test_login_screen_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/api-explorer')->assertRedirect('/login');
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_users_can_log_in_and_session_is_regenerated(): void
    {
        $user = $this->developer();
        $oldSession = session()->getId();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('explorer.index'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSession, session()->getId());
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $user->id]);
    }

    public function test_wrong_password_is_rejected_with_generic_message(): void
    {
        $user = $this->developer();

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

        $this->assertGuest();
    }

    public function test_login_is_throttled_after_too_many_attempts(): void
    {
        config(['paystack.rate_limits.login_attempts' => 3]);
        $user = $this->developer();

        foreach (range(1, 3) as $i) {
            $this->post('/login', ['email' => $user->email, 'password' => 'nope']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertTrue(AuditLog::where('action', 'auth.login_throttled')->exists());
    }

    public function test_suspended_users_cannot_log_in(): void
    {
        $user = User::factory()->suspended()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Your account has been suspended. Contact an administrator.']);

        $this->assertGuest();
    }

    public function test_remember_me_sets_a_recaller_cookie(): void
    {
        $user = $this->developer();

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => '1']);

        $recaller = collect($response->headers->getCookies())->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));
        $this->assertNotNull($recaller);
        $this->assertTrue($recaller->isHttpOnly());
    }

    public function test_users_can_log_out(): void
    {
        $user = $this->developer();

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_registration_creates_a_developer_never_an_admin(): void
    {
        app(Settings::class)->set('default_role', 'admin');

        $this->post('/register', [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'CorrectHorse99',
            'password_confirmation' => 'CorrectHorse99',
        ])->assertRedirect(route('explorer.index'));

        $user = User::where('email', 'ada@example.com')->firstOrFail();
        $this->assertSame(Role::Developer, $user->role);
        $this->assertNotSame('CorrectHorse99', $user->password, 'Password must be hashed');
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_enforces_password_rules(): void
    {
        $this->post('/register', [
            'name' => 'Weak',
            'email' => 'weak@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }

    public function test_registration_can_be_disabled(): void
    {
        app(Settings::class)->set('registration_enabled', false);

        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'x'])->assertNotFound();
    }
}
