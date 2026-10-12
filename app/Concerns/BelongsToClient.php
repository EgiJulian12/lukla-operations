<?php

namespace App\Concerns;

use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lapisan pertama isolasi multi-tenant (NFR-004, FR-025).
 *
 * Global scope ini membatasi query HANYA ketika pengguna yang login adalah
 * akun klien (user dengan client_id terisi). Staf internal — yang memang
 * bekerja lintas klien — tidak terkena scope ini.
 *
 * Penting: karena ini global scope Eloquent, proteksi berlaku juga di luar
 * panel Filament — perintah artisan, queued job, dan seeder — berbeda dengan
 * tenancy bawaan Filament yang hanya aktif setelah identifikasi tenant di
 * middleware HTTP.
 *
 * Ini BUKAN satu-satunya lapisan. Lihat juga:
 *  - app/Policies/* — otorisasi tingkat aksi per peran
 *  - inventory_movements — foreign key komposit penjaga kecocokan klien/pesanan
 */
trait BelongsToClient
{
    public static function bootBelongsToClient(): void
    {
        static::addGlobalScope('client', function (Builder $builder): void {
            $clientId = static::resolveCurrentClientId();

            // null berarti staf internal atau konteks tanpa autentikasi
            // (seeder, migrasi, perintah artisan) — tidak di-scope.
            if ($clientId !== null) {
                $builder->where(
                    $builder->getModel()->qualifyColumn('client_id'),
                    $clientId,
                );
            }
        });
    }

    /**
     * Klien yang sedang aktif, diambil dari akun yang login.
     * Dikembalikan null untuk staf internal sehingga query tidak dibatasi.
     */
    protected static function resolveCurrentClientId(): ?int
    {
        if (! app()->bound('auth')) {
            return null;
        }

        $user = auth()->user();

        return $user?->client_id !== null ? (int) $user->client_id : null;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Jalur keluar yang disengaja untuk pelaporan internal lintas klien.
     * Pemakaian harus eksplisit dan terlihat di kode yang memanggilnya.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForClient(Builder $query, Client|int $client): Builder
    {
        return $query
            ->withoutGlobalScope('client')
            ->where(
                $query->getModel()->qualifyColumn('client_id'),
                $client instanceof Client ? $client->getKey() : $client,
            );
    }
}
