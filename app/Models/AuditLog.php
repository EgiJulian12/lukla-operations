<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/**
 * Jejak audit sistem (FR-022, NFR-005, NFR-006).
 *
 * Model ini sengaja bersifat append-only. Baris audit tidak boleh diubah atau
 * dihapus — upaya apa pun dilempar sebagai RuntimeException, sehingga jalur
 * penyuntingan riwayat tidak tersedia bahkan lewat kode aplikasi.
 *
 * Gunakan AuditLog::record() alih-alih create() langsung: method itulah yang
 * menghitung rantai hash sehingga integritas baris dapat diverifikasi.
 *
 * @property-read string $payload_hash
 * @property-read string|null $previous_hash
 */
class AuditLog extends Model
{
    use HasFactory;

    /**
     * Hanya created_at yang dikelola; audit tidak punya updated_at.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'auditable_type',
        'auditable_id',
        'event',
        'client_id',
        'order_id',
        'before',
        'after',
        'reason',
        'payload_hash',
        'previous_hash',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('Baris audit tidak dapat diubah — jejak audit bersifat append-only.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('Baris audit tidak dapat dihapus — jejak audit bersifat append-only.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Menambah satu baris audit, merantai hash-nya ke baris terakhir.
     *
     * Dinamai record(), bukan append(), karena Eloquent\Model sudah memiliki
     * append() non-statis untuk menambah accessor.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function record(array $attributes): self
    {
        $previousHash = static::query()->latest('id')->value('payload_hash');

        $log = new self($attributes);
        $log->previous_hash = $previousHash;
        $log->payload_hash = $log->computePayloadHash($previousHash);
        $log->save();

        return $log;
    }

    /**
     * Memverifikasi bahwa baris ini masih cocok dengan rantai hash-nya —
     * dipakai untuk membuktikan jejak audit belum disunting (NFR-005).
     */
    public function verifyChain(): bool
    {
        return hash_equals($this->payload_hash, $this->computePayloadHash($this->previous_hash));
    }

    protected function computePayloadHash(?string $previousHash): string
    {
        return hash('sha256', json_encode([
            'auditable_type' => $this->auditable_type,
            'auditable_id' => $this->auditable_id,
            'event' => $this->event,
            'client_id' => $this->client_id,
            'order_id' => $this->order_id,
            'before' => $this->before,
            'after' => $this->after,
            'reason' => $this->reason,
            'previous_hash' => $previousHash,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
