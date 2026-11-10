<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Notifications\ResetPasswordLink;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Role $role
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $deactivated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * New users get the least powerful role until an admin decides otherwise.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => Role::Viewer->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => Role::class,
            'deactivated_at' => 'datetime',
        ];
    }

    /**
     * People who can be made responsible for suppliers and contracts.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function activeBuyersAndAdmins(Builder $query): void
    {
        $query->whereIn('role', [Role::Admin, Role::Buyer])->whereNull('deactivated_at');
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    public function status(): UserStatus
    {
        return match (true) {
            $this->isDeactivated() => UserStatus::Deactivated,
            $this->password === null => UserStatus::Invited,
            default => UserStatus::Active,
        };
    }

    /**
     * @param  string  $token
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordLink($token));
    }
}
