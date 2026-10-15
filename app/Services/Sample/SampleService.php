<?php

namespace App\Services\Sample;

use App\Enums\OrderStatus;
use App\Enums\SampleStatus;
use App\Enums\WorkScheme;
use App\Exceptions\InvalidSampleState;
use App\Models\Article;
use App\Models\Component;
use App\Models\Order;
use App\Models\Sample;
use App\Models\SampleRevision;
use App\Models\TimeStudy;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Satu-satunya jalur tulis untuk sample, revisi, time study, dan konversinya
 * menjadi order massal (FR-001 sampai FR-004).
 *
 * Revisi bersifat append-only: mengubah spesifikasi berarti menambah versi
 * baru, bukan menimpa versi lama, sehingga riwayat tetap dapat ditelusuri
 * sampai ke order yang diproduksi massal (BR-005).
 */
class SampleService
{
    /**
     * Membuat sample baru dalam status draf (FR-001). Pemilik klien mengikuti
     * artikel, bukan input pemanggil.
     */
    public function createSample(
        Article $article,
        string $code,
        string $title,
        ?string $notes = null,
    ): Sample {
        return Sample::create([
            'client_id' => $article->client_id,
            'article_id' => $article->getKey(),
            'code' => $code,
            'title' => $title,
            'status' => SampleStatus::DRAFT,
            'notes' => $notes,
        ]);
    }

    /**
     * Menambah revisi berikutnya (v1, v2, dst) dan menjadikannya revisi terkini
     * (FR-001). Revisi yang sudah disetujui tidak diubah oleh operasi ini, hanya
     * ditambah.
     *
     * @param  array<string, mixed>  $specs
     * @param  array<string, mixed>  $materialUsage
     */
    public function addRevision(
        Sample $sample,
        array $specs = [],
        array $materialUsage = [],
        ?string $notes = null,
    ): SampleRevision {
        return DB::transaction(function () use ($sample, $specs, $materialUsage, $notes): SampleRevision {
            $revision = SampleRevision::create([
                'client_id' => $sample->client_id,
                'sample_id' => $sample->getKey(),
                'version' => $sample->nextRevisionNumber(),
                'specs' => $specs,
                'material_usage' => $materialUsage,
                'status' => SampleStatus::DRAFT,
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);

            $sample->update([
                'current_revision_id' => $revision->getKey(),
                'status' => SampleStatus::DRAFT,
            ]);

            return $revision;
        });
    }

    /**
     * Mencatat durasi time study sebuah komponen pada satu revisi (FR-002).
     */
    public function recordTimeStudy(
        SampleRevision $revision,
        Component $component,
        int $durationSeconds,
        ?string $notes = null,
    ): TimeStudy {
        if ($durationSeconds <= 0) {
            throw new InvalidArgumentException('Durasi time study harus lebih besar dari nol detik.');
        }

        return TimeStudy::create([
            'client_id' => $revision->client_id,
            'sample_revision_id' => $revision->getKey(),
            'component_id' => $component->getKey(),
            'duration_seconds' => $durationSeconds,
            'recorded_by' => auth()->id(),
            'recorded_at' => now(),
            'notes' => $notes,
        ]);
    }

    /**
     * Mengajukan revisi untuk disetujui (FR-003). Langkah ini memisahkan
     * penyusun sample dari penyetujunya.
     */
    public function submitForReview(SampleRevision $revision): SampleRevision
    {
        $revision->update(['status' => SampleStatus::IN_REVIEW]);

        return $revision->refresh();
    }

    /**
     * Menyetujui revisi dan menandainya sebagai revisi yang sah untuk produksi
     * massal (FR-003, FR-026).
     */
    public function approveRevision(SampleRevision $revision, ?User $approver = null): SampleRevision
    {
        if (! $revision->status->isApprovable()) {
            throw InvalidSampleState::notApprovable($revision);
        }

        return DB::transaction(function () use ($revision, $approver): SampleRevision {
            $revision->update([
                'status' => SampleStatus::APPROVED,
                'approved_by' => $approver?->getKey() ?? auth()->id(),
                'approved_at' => now(),
            ]);

            $revision->sample->update([
                'status' => SampleStatus::APPROVED,
                'approved_revision_id' => $revision->getKey(),
            ]);

            return $revision->refresh();
        });
    }

    public function rejectRevision(SampleRevision $revision, ?string $reason = null): SampleRevision
    {
        return DB::transaction(function () use ($revision, $reason): SampleRevision {
            $revision->update([
                'status' => SampleStatus::REJECTED,
                'notes' => $reason ?? $revision->notes,
            ]);

            $revision->sample->update(['status' => SampleStatus::REJECTED]);

            return $revision->refresh();
        });
    }

    /**
     * Membuat order produksi massal dari sample yang sudah disetujui (FR-004).
     *
     * Order membawa revisi yang disetujui, sehingga spesifikasi yang diproduksi
     * selalu dapat ditelusuri kembali ke versi sample-nya.
     */
    public function convertToOrder(
        Sample $sample,
        WorkScheme $scheme,
        int $quantity,
        DateTimeInterface|string|null $dueDate = null,
        ?string $notes = null,
    ): Order {
        if (! $sample->isReadyForMassProduction()) {
            throw InvalidSampleState::notReadyForOrder($sample);
        }

        if ($quantity < 1) {
            throw new InvalidArgumentException('Jumlah order massal harus minimal satu unit.');
        }

        $parsedDueDate = is_string($dueDate) && filled($dueDate)
            ? Carbon::parse($dueDate)
            : $dueDate;

        return DB::transaction(function () use ($sample, $scheme, $quantity, $parsedDueDate, $notes): Order {
            $revision = $sample->approvedRevision;

            return Order::create([
                'client_id' => $sample->client_id,
                'article_id' => $sample->article_id,
                'sample_id' => $sample->getKey(),
                'sample_revision_id' => $revision->getKey(),
                'order_number' => $this->nextOrderNumber($sample, $revision),
                'work_scheme' => $scheme,
                'quantity' => $quantity,
                'status' => OrderStatus::CONFIRMED,
                'due_date' => $parsedDueDate,
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Nomor order diturunkan dari kode sample dan versi yang dikonversi, lalu
     * diberi akhiran urut bila sample yang sama dikonversi lebih dari sekali.
     */
    private function nextOrderNumber(Sample $sample, SampleRevision $revision): string
    {
        $base = sprintf('MO-%s-v%d', $sample->code, $revision->version);

        $used = Order::withTrashed()
            ->where('order_number', 'like', $base.'%')
            ->count();

        return $used === 0 ? $base : $base.'-'.($used + 1);
    }
}
