<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Settings;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function __construct(
        private readonly Settings $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function show(): View
    {
        abort_unless($this->settings->bool('registration_enabled'), 404);

        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        abort_unless($this->settings->bool('registration_enabled'), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // Self-registration can never produce an admin.
        $role = Role::tryFrom((string) $this->settings->get('default_role'));
        if ($role === null || $role === Role::Admin) {
            $role = Role::Developer;
        }

        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        $user->role = $role;
        $user->status = UserStatus::Active;
        $user->save();

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        $this->audit->record('auth.registered', $user, ['role' => $role->value], $user->id);

        return redirect()->route('explorer.index');
    }
}
