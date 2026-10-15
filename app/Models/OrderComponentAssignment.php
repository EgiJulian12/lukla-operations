<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penjahit yang memegang satu komponen pada sebuah order (FR-009).
 *
 * Baris lahir bersama transisi status order ke Dalam Produksi dan tidak
 * disunting lepas dari transisi itu, sehingga rencana kerja dan status order
 * selalu sejalan. Lihat ProductionService::startProduction().
 */
class OrderComponentAssignment extends Model
{
    use HasFactory;

    private ?OutputEntry $cachedLatestOutput = null;

    private bool $hasResolvedLatestOutput = false;

    private ?bool $cachedHasReachedQcTarget = null;

    protected $fillable = [
        'order_id',
        'component_id',
        'user_id',
        'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    /**
     * Penjahit pemegang komponen. Dinamai tailor, bukan user, karena kolomnya
     * memang tidak pernah berisi peran lain — lihat ProductionService.
     */
    public function tailor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Laporan output terakhir penjahit untuk komponen ini.
     *
     * Satu penjahit dapat melaporkan komponen yang sama lebih dari sekali —
     * misalnya setelah laporannya ditolak mandor — sehingga keadaan tugas saat
     * ini ditentukan catatan terakhir, bukan yang pertama.
     *
     * Hasilnya diingat selama instance ini hidup karena satu baris tabel
     * pekerjaan penjahit membacanya beberapa kali dalam satu render.
     */
    public function latestOutput(): ?OutputEntry
    {
        if (! $this->hasResolvedLatestOutput) {
            $this->cachedLatestOutput = OutputEntry::query()
                ->where('order_id', $this->order_id)
                ->where('component_id', $this->component_id)
                ->where('user_id', $this->user_id)
                ->latest('interval_start')
                ->latest('id')
                ->first();

            $this->hasResolvedLatestOutput = true;
        }

        return $this->cachedLatestOutput;
    }

    /**
     * Apakah tugas komponen ini sudah selesai — target QC order-nya tercapai.
     *
     * Inilah yang menutup tugas di daftar kerja penjahit. Batasnya diletakkan
     * di tingkat order, bukan di tingkat laporan: satu komponen masih perlu
     * dikerjakan selama order-nya belum menyelesaikan pemeriksaan QC, sehingga
     * penjahit dapat terus melaporkan output intervalnya sampai unit yang lolos
     * QC mencukupi jumlah yang dipesan. Lihat Order::hasReachedQcTarget().
     *
     * Hasilnya diingat selama instance ini hidup karena satu baris tabel
     * pekerjaan penjahit menanyakannya lebih dari sekali dalam satu render.
     */
    public function hasReachedQcTarget(): bool
    {
        if ($this->cachedHasReachedQcTarget !== null) {
            return $this->cachedHasReachedQcTarget;
        }

        return $this->cachedHasReachedQcTarget = $this->order?->hasReachedQcTarget() ?? false;
    }
}
