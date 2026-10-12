<?php

namespace App\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Menulis jejak audit untuk model yang memakainya (FR-022, NFR-005).
 *
 * Dipisahkan sebagai trait agar modul berikutnya (Order, RateCard,
 * SampleRevision, OutputEntry, QcRecord, PayrollPeriod) dapat memakai pola
 * yang sama tanpa mengulang kode.
 *
 * Perubahan kolom biasa terekam otomatis lewat event Eloquent. Kejadian domain
 * yang bukan sekadar perubahan kolom — penerbitan bahan, verifikasi output,
 * persetujuan sample — dipanggil eksplisit lewat recordAudit().
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            $model->recordAudit('created', ['after' => $model->attributesToArray()]);
        });

        static::updated(function (Model $model): void {
            $model->recordAudit('updated', [
                'before' => $model->getOriginal(),
                'after' => $model->getChanges(),
            ]);
        });

        static::deleted(function (Model $model): void {
            $model->recordAudit('deleted', ['before' => $model->attributesToArray()]);
        });
    }

    /**
     * Merekam satu kejadian audit untuk model ini.
     *
     * client_id dan order_id diambil dari model bila ada, sehingga baris audit
     * tetap dapat disaring per klien untuk portal dan pelaporan.
     *
     * @param  array{before?: array<string, mixed>|null, after?: array<string, mixed>|null, reason?: string|null}  $meta
     */
    public function recordAudit(string $event, array $meta = []): AuditLog
    {
        return AuditLog::record([
            'user_id' => auth()->id(),
            'auditable_type' => $this->getMorphClass(),
            'auditable_id' => $this->getKey(),
            'event' => $event,
            'client_id' => $this->getAttribute('client_id'),
            'order_id' => $this->getAttribute('order_id'),
            'before' => $meta['before'] ?? null,
            'after' => $meta['after'] ?? null,
            'reason' => $meta['reason'] ?? null,
        ]);
    }

    /**
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
