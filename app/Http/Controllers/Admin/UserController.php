<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $q = $request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? null;

        return view('admin.users.index', [
            'users' => User::withCount('requestLogs')
                ->when($q, fn ($query) => $query->where(fn ($w) => $w
                    ->where('name', 'like', '%'.addcslashes($q, '%_').'%')
                    ->orWhere('email', 'like', '%'.addcslashes($q, '%_').'%')))
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
            'q' => $q,
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', ['user' => new User, 'roles' => Role::cases(), 'statuses' => UserStatus::cases()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', new Enum(Role::class)],
            'status' => ['required', new Enum(UserStatus::class)],
        ]);

        $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        $user->role = Role::from($data['role']);
        $user->status = UserStatus::from($data['status']);
        $user->can_use_live = $request->boolean('can_use_live');
        $user->save();

        $this->audit->record('admin.user_created', $user, ['email' => $user->email, 'role' => $user->role->value]);

        return redirect()->route('admin.users.index')->with('status', "User {$user->email} created.");
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', ['user' => $user, 'roles' => Role::cases(), 'statuses' => UserStatus::cases()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', new Enum(Role::class)],
            'status' => ['required', new Enum(UserStatus::class)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        $before = ['role' => $user->role->value, 'status' => $user->status->value, 'can_use_live' => $user->can_use_live];

        $user->fill(['name' => $data['name'], 'email' => $data['email']]);

        if ($request->user()->can('changeAccess', $user)) {
            $user->role = Role::from($data['role']);
            $user->status = UserStatus::from($data['status']);
            $user->can_use_live = $request->boolean('can_use_live');
        } elseif ($data['role'] !== $user->role->value || $data['status'] !== $user->status->value) {
            return back()->withErrors(['role' => 'You cannot change your own role or status.']);
        }

        if (! empty($data['password'])) {
            $user->password = $data['password'];
            $user->remember_token = Str::random(60);
        }

        $user->save();

        // A suspended user is signed out everywhere immediately.
        if ($user->status === UserStatus::Suspended && config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        $this->audit->record('admin.user_updated', $user, [
            'before' => $before,
            'after' => ['role' => $user->role->value, 'status' => $user->status->value, 'can_use_live' => $user->can_use_live],
            'password_changed' => ! empty($data['password']),
        ]);

        return redirect()->route('admin.users.index')->with('status', "User {$user->email} updated.");
    }
}
