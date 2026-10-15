<?php

namespace App\Models;

use App\Enums\UserRole;
use Closure;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;

/**
 * Pengguna sistem.
 *
 * Dua kelompok pengguna dengan jalur akses terpisah:
 *  - Staf internal (7 peran) memakai panel /admin dan bekerja lintas klien.
 *  - Klien maklon memakai panel /portal dan hanya melihat client_id-nya sendiri.
 *
 * canAccessPanel() WAJIB diimplementasikan: tanpa itu Filament mengizinkan
 * semua pengguna terautentikasi masuk ke panel mana pun saat APP_ENV bukan local.
 */
class User extends Authenticatable implements FilamentUser, HasName, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;

    protected $fillable = [
        'client_id',
        'telegram_chat_id',
        'name',
        'email',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Klien pemilik akun ini. Terisi hanya untuk pengguna klien maklon.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Komponen yang dipegang penjahit ini pada order-order produksi (FR-009).
     * Terisi hanya untuk peran Penjahit; peran lain tidak pernah ditugaskan.
     *
     * @return HasMany<OrderComponentAssignment, $this>
     */
    public function componentAssignments(): HasMany
    {
        return $this->hasMany(OrderComponentAssignment::class);
    }

    // -----------------------------------------------------------------
    // Pembatasan akses panel
    // -----------------------------------------------------------------

    /**
     * Memisahkan panel internal dan portal klien, dan menolak akun nonaktif.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return match ($panel->getId()) {
            'admin' => $this->hasAnyRole(self::internalRoleNames()),
            'portal' => $this->hasRole(UserRole::CLIENT_MAKLON->value),
            default => false,
        };
    }

    // -----------------------------------------------------------------
    // Multi-tenancy panel /portal
    // -----------------------------------------------------------------

    /**
     * Klien yang boleh diakses pengguna ini — hanya kliennya sendiri (NFR-004).
     */
    public function getTenants(Panel $panel): array|Collection
    {
        if ($this->client_id === null) {
            return [];
        }

        return Client::query()->whereKey($this->client_id)->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant instanceof Client
            && $this->client_id !== null
            && (int) $this->client_id === (int) $tenant->getKey();
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    // -----------------------------------------------------------------
    // Bantuan peran
    // -----------------------------------------------------------------

    /**
     * @return array<int, string>
     */
    public static function internalRoleNames(): array
    {
        return array_map(
            fn (UserRole $role): string => $role->value,
            UserRole::internal(),
        );
    }

    public function isClientUser(): bool
    {
        return $this->hasRole(UserRole::CLIENT_MAKLON->value);
    }

    /**
     * Apakah salah satu peran pengguna memenuhi predikat yang diberikan.
     *
     * Dipakai policy agar aturan peran hanya ditulis sekali, pada enum UserRole
     * (mis. canManageInventory), bukan diulang sebagai daftar nama role.
     *
     * @param  Closure(UserRole): bool  $predicate
     */
    public function hasRoleSatisfying(Closure $predicate): bool
    {
        return $this->getRoleNames()->contains(
            fn (string $name): bool => ($role = UserRole::tryFrom($name)) !== null && $predicate($role),
        );
    }

    /**
     * Peran pertama yang dimiliki, dipakai untuk menampilkan label di UI.
     */
    public function primaryRole(): ?UserRole
    {
        $name = $this->getRoleNames()->first();

        return $name !== null ? UserRole::tryFrom($name) : null;
    }

    public function primaryRoleLabel(): string
    {
        return $this->primaryRole()?->getLabel() ?? 'Tanpa Peran';
    }
}
