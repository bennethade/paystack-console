<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * Forgot / reset password using Laravel's built-in password broker
 * (password_reset_tokens table + ResetPassword notification).
 */
class PasswordResetController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        $status = Password::sendResetLink($request->only('email'));
        $this->audit->record('auth.password_reset_requested', meta: ['email' => $request->input('email'), 'broker_status' => $status]);

        // Same response whether or not the account exists (no user enumeration).
        return back()->with('status', 'If an account exists for that email address, a password reset link has been sent.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
                $this->audit->record('auth.password_reset', $user, userId: $user->id);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
        }

        return redirect()->route('login')->with('status', 'Your password has been reset. You can now sign in.');
    }
}
