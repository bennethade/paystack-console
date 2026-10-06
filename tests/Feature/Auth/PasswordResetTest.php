<?php

namespace Tests\Feature\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_is_sent(): void
    {
        Notification::fake();
        $user = $this->developer();

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_email_gets_the_same_response(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertSessionHas('status', 'If an account exists for that email address, a password reset link has been sent.');

        Notification::assertNothingSent();
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        Notification::fake();
        $user = $this->developer();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $this->get('/reset-password/'.$notification->token.'?email='.urlencode($user->email))->assertOk();

            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'BrandNewPass123',
                'password_confirmation' => 'BrandNewPass123',
            ])->assertRedirect('/login');

            return true;
        });

        $this->assertTrue(Hash::check('BrandNewPass123', $user->fresh()->password));
    }

    public function test_invalid_token_is_rejected(): void
    {
        $user = $this->developer();

        $this->post('/reset-password', [
            'token' => 'invalid',
            'email' => $user->email,
            'password' => 'BrandNewPass123',
            'password_confirmation' => 'BrandNewPass123',
        ])->assertSessionHasErrors('email');
    }
}
