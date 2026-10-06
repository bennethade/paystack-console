<?php

namespace App\Http\Controllers;

use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Profile, password change and session management for the signed-in user.
 */
class AccountController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function edit(Request $request): View
    {
        return view('account.edit', [
            'user' => $request->user(),
            'sessions' => $this->sessions($request),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->update($data);

        return back()->with('status', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();
        $user->forceFill([
            'password' => $request->input('password'),
            'remember_token' => Str::random(60),
        ])->save();

        $this->deleteOtherSessions($request);
        $request->session()->regenerate();
        $this->audit->record('account.password_changed', $user);

        return back()->with('status', 'Password changed. Other sessions have been signed out.');
    }

    public function destroyOtherSessions(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $user = $request->user();
        // Rotating the remember token invalidates "remember me" cookies elsewhere.
        $user->forceFill(['remember_token' => Str::random(60)])->save();
        $this->deleteOtherSessions($request);
        $this->audit->record('account.sessions_revoked', $user);

        return back()->with('status', 'All other sessions have been signed out.');
    }

    private function sessions(Request $request): array
    {
        if (config('session.driver') !== 'database') {
            return [];
        }

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($s) => [
                'ip' => $s->ip_address,
                'agent' => Str::limit((string) $s->user_agent, 90),
                'last_active' => now()->setTimestamp($s->last_activity),
                'current' => $s->id === $request->session()->getId(),
            ])
            ->all();
    }

    private function deleteOtherSessions(Request $request): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();
    }
}
