<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'status' => UserStatus::class,
            'can_use_live' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($permission === 'explorer.execute-live' && ! $this->can_use_live) {
            return false;
        }

        return $this->role->grants($permission);
    }

    public function favoriteEndpoints(): BelongsToMany
    {
        return $this->belongsToMany(Endpoint::class, 'user_favorites')->withTimestamps();
    }

    public function preference(): HasOne
    {
        return $this->hasOne(UserApiPreference::class);
    }

    public function requestLogs(): HasMany
    {
        return $this->hasMany(ApiRequestLog::class);
    }

    public function savedRequests(): HasMany
    {
        return $this->hasMany(SavedRequest::class);
    }
}
