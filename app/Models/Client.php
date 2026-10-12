<?php

namespace App\Models;

use App\Enums\ClientType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Klien — akar multi-tenant. Snøhund (brand internal) disimpan sebagai baris
 * klien biasa dengan type = internal, sehingga aturan isolasi data yang sama
 * berlaku untuk order internal maupun maklon.
 */
class Client extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'type',
        'contact_person',
        'phone',
        'email',
        'address',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ClientType::class,
            'is_active' => 'boolean',
        ];
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function samples(): HasMany
    {
        return $this->hasMany(Sample::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function rateCards(): HasMany
    {
        return $this->hasMany(RateCard::class);
    }

    public function isInternal(): bool
    {
        return $this->type === ClientType::INTERNAL;
    }

    /**
     * Dipakai sebagai label pada tenant menu panel /portal.
     */
    public function getFilamentName(): string
    {
        return $this->name;
    }
}
