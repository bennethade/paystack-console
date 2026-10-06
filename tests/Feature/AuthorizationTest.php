<?php

namespace Tests\Feature;

use App\Models\ApiRequestLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPaystack();
    }

    public function test_viewers_can_browse_but_not_execute(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get('/api-explorer/transactions/initialize')->assertOk()->assertSee('Read-only role');
        $this->actingAs($viewer)->execute('transaction.initialize', ['email' => 'a@b.co', 'amount' => 5000])->assertForbidden();
    }

    public function test_viewers_cannot_see_webhooks(): void
    {
        $this->actingAs(User::factory()->viewer()->create())->get('/api-explorer/webhooks')->assertForbidden();
    }

    public function test_developers_cannot_access_admin(): void
    {
        $developer = $this->developer();

        foreach (['/admin', '/admin/users', '/admin/endpoints', '/admin/settings', '/admin/countries', '/admin/logs'] as $url) {
            $this->actingAs($developer)->get($url)->assertForbidden();
        }
    }

    public function test_admins_can_access_admin(): void
    {
        $admin = $this->admin();

        foreach (['/admin', '/admin/users', '/admin/endpoints', '/admin/settings', '/admin/countries', '/admin/currencies', '/admin/categories', '/admin/logs', '/admin/audit'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_users_cannot_view_or_delete_someone_elses_history(): void
    {
        $owner = $this->developer();
        $log = ApiRequestLog::create([
            'user_id' => $owner->id, 'endpoint_key' => 'bank.list', 'environment' => 'test',
            'method' => 'GET', 'path' => '/bank', 'url' => 'https://api.paystack.co/bank',
        ]);

        $intruder = $this->developer();
        $this->actingAs($intruder)->get(route('explorer.history.show', $log))->assertForbidden();
        $this->actingAs($intruder)->get(route('explorer.history.replay', $log))->assertForbidden();
        $this->actingAs($intruder)->delete(route('explorer.history.destroy', $log))->assertForbidden();

        $this->actingAs($this->admin())->get(route('explorer.history.show', $log))->assertOk();
    }

    public function test_a_user_suspended_mid_session_is_signed_out(): void
    {
        $user = $this->developer();
        $this->actingAs($user)->get('/api-explorer')->assertOk();

        $user->forceFill(['status' => 'suspended'])->save();

        $this->actingAs($user->fresh())->get('/api-explorer')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_admins_cannot_change_their_own_role(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'viewer', 'status' => 'active',
        ])->assertSessionHasErrors('role');

        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_live_permission_requires_the_user_flag(): void
    {
        $this->assertFalse($this->developer()->hasPermission('explorer.execute-live'));
        $this->assertTrue(User::factory()->liveEnabled()->create()->hasPermission('explorer.execute-live'));
        $this->assertFalse(User::factory()->viewer()->liveEnabled()->create()->hasPermission('explorer.execute-live'));
    }
}
