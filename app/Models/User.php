<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Lab404\Impersonate\Models\Impersonate;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use BelongsToTenant, HasFactory, HasRoles, Impersonate, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['tenant_id', 'name', 'email', 'password', 'is_superadmin'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = ['password', 'badge_pin', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'];

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
            'is_superadmin' => 'bool',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function hotels(): BelongsToMany
    {
        return $this->belongsToMany(Hotel::class)->withTimestamps();
    }

    public function activeHotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class, 'active_hotel_id');
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /**
     * @return Collection<int, PushSubscription>
     */
    public function routeNotificationForWebPush(): Collection
    {
        return $this->pushSubscriptions()
            ->where('tenant_id', $this->tenant_id)
            ->get();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'superadmin') {
            return (bool) $this->is_superadmin;
        }

        return false;
    }

    public function canImpersonate(): bool
    {
        return (bool) $this->is_superadmin;
    }

    public function canBeImpersonated(): bool
    {
        return ! $this->is_superadmin;
    }

    public function activeCashSession(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CashSession::class, 'opened_by_user_id')
            ->where('status', 'open')
            ->latestOfMany();
    }
}
