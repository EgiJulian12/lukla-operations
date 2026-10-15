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
use App\Services\Telegram\TelegramNotifier;
use App\Services\Inventory\OrderMaterialFlowService;
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
    public function __construct(private readonly TelegramNotifier $telegram) {}

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
        ?string $resultImagePath = null,
    ): SampleRevision {
        return DB::transaction(function () use ($sample, $specs, $materialUsage, $notes, $resultImagePath): SampleRevision {
            $revision = SampleRevision::create([
                'client_id' => $sample->client_id,
                'sample_id' => $sample->getKey(),
                'version' => $sample->nextRevisionNumber(),
                'specs' => $specs,
                'material_usage' => $materialUsage,
                'result_image_path' => $resultImagePath,
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
     * Inisialisasi Artikel dan Sample v1 (DRAFT) dari sebuah Order yang sudah di-ACC.
     *
     * @param  array{name: string, code: string, description?: ?string}  $articleData
     */
    public function initializeSampleFromOrder(Order $order, array $articleData): Sample
    {
        return DB::transaction(function () use ($order, $articleData): Sample {
            $article = Article::create([
                'client_id' => $order->client_id,
                'order_id' => $order->getKey(),
                'name' => $articleData['name'],
                'code' => $articleData['code'],
                'description' => $articleData['description'] ?? null,
            ]);

            $order->update([
                'article_id' => $article->getKey(),
                'sample_requested_at' => now(),
            ]);

            $sample = $this->createSample(
                article: $article,
                code: $articleData['code'],
                title: $articleData['name'],
                notes: $articleData['description'] ?? null,
            );

            $this->addRevision(
                sample: $sample,
                specs: [
                    'design_images' => $order->design_images ?? [],
                    'description' => $order->description,
                    'order_number' => $order->order_number,
                ],
                materialUsage: [],
                notes: 'Sample perdana (v1)',
            );

            $order->update([
                'sample_id' => $sample->getKey(),
            ]);

            return $sample->refresh();
        });
    }

    /**
     * Menyinkronkan daftar komponen artikel dan time study pada suatu revisi sample (FR-001, FR-002).
     *
     * @param  array<int, array{component_name: string, duration_seconds: int|numeric, notes?: ?string}>  $items
     */
    public function syncComponentsAndTimeStudies(SampleRevision $revision, array $items): void
    {
        if (! $revision->isEditable()) {
            throw InvalidSampleState::notEditable($revision);
        }

        DB::transaction(function () use ($revision, $items): void {
            $article = $revision->sample->article;
            $updatedTimeStudyIds = [];

            foreach ($items as $item) {
                $componentName = trim((string) ($item['component_name'] ?? ''));
                if ($componentName === '') {
                    continue;
                }

                $component = Component::where('article_id', $article->getKey())
                    ->where('name', $componentName)
                    ->first();

                if (! $component) {
                    $nextSeq = ((int) Component::where('article_id', $article->getKey())->max('sequence')) + 1;
                    $code = sprintf('KOMP-%03d', $nextSeq);
                    while (Component::where('article_id', $article->getKey())->where('code', $code)->exists()) {
                        $nextSeq++;
                        $code = sprintf('KOMP-%03d', $nextSeq);
                    }

                    $component = Component::create([
                        'article_id' => $article->getKey(),
                        'name' => $componentName,
                        'code' => $code,
                        'sequence' => $nextSeq,
                    ]);
                }

                $duration = (int) ($item['duration_seconds'] ?? 0);
                $timeStudy = TimeStudy::updateOrCreate(
                    [
                        'sample_revision_id' => $revision->getKey(),
                        'component_id' => $component->getKey(),
                    ],
                    [
                        'client_id' => $revision->client_id,
                        'duration_seconds' => max(1, $duration),
                        'notes' => $item['notes'] ?? null,
                        'recorded_by' => auth()->id(),
                        'recorded_at' => now(),
                    ]
                );

                $updatedTimeStudyIds[] = $timeStudy->getKey();
            }

            TimeStudy::where('sample_revision_id', $revision->getKey())
                ->whereNotIn('id', $updatedTimeStudyIds)
                ->delete();
        });
    }

    /**
     * Membuat revisi berikutnya dari revisi yang sudah ada (Cloning Revisi v1 -> v2) (FR-3.1, Skenario Uji 04).
     *
     * Revisi lama ditandai sebagai REVISED (read-only, append-only audit trail).
     * Seluruh spesifikasi, estimasi bahan, dan komponen time study disalin ke revisi baru (DRAFT).
     *
     * @param  array<string, mixed>  $overrides
     */
    public function createRevisionFromPrevious(
        SampleRevision $previous,
        array $overrides = [],
        ?string $notes = null,
    ): SampleRevision {
        if (! $previous->canBeRevised()) {
            throw InvalidSampleState::cannotBeRevised($previous);
        }

        return DB::transaction(function () use ($previous, $overrides, $notes): SampleRevision {
            // Tandai revisi sebelumnya sebagai telah direvisi (REVISED)
            $previous->update(['status' => SampleStatus::REVISED]);

            $sample = $previous->sample;

            $newRevision = SampleRevision::create([
                'client_id' => $previous->client_id,
                'sample_id' => $previous->sample_id,
                'parent_revision_id' => $previous->getKey(),
                'version' => $sample->nextRevisionNumber(),
                'specs' => $overrides['specs'] ?? $previous->specs,
                'material_usage' => $overrides['material_usage'] ?? $previous->material_usage,
                'result_image_path' => $overrides['result_image_path'] ?? $previous->result_image_path,
                'status' => SampleStatus::DRAFT,
                'notes' => $notes ?? $overrides['notes'] ?? sprintf('Revisi dari %s', $previous->version_label),
                'created_by' => auth()->id(),
            ]);

            // Salin seluruh baris time study dari revisi lama ke revisi baru
            foreach ($previous->timeStudies as $timeStudy) {
                TimeStudy::create([
                    'client_id' => $newRevision->client_id,
                    'sample_revision_id' => $newRevision->getKey(),
                    'component_id' => $timeStudy->component_id,
                    'duration_seconds' => $timeStudy->duration_seconds,
                    'recorded_by' => auth()->id(),
                    'recorded_at' => now(),
                    'notes' => $timeStudy->notes,
                ]);
            }

            $sample->update([
                'current_revision_id' => $newRevision->getKey(),
                'status' => SampleStatus::DRAFT,
            ]);

            return $newRevision->refresh();
        });
    }

    /**
     * Mencatat durasi time study sebuah komponen pada satu revisi (FR-002).
     * Jika durasi untuk komponen pada revisi ini sudah ada, perbarui nilainya.
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

        $existing = TimeStudy::query()
            ->where('sample_revision_id', $revision->getKey())
            ->where('component_id', $component->getKey())
            ->first();

        if ($existing !== null) {
            $existing->update([
                'duration_seconds' => $durationSeconds,
                'recorded_by' => auth()->id(),
                'recorded_at' => now(),
                'notes' => $notes ?? $existing->notes,
            ]);

            return $existing->refresh();
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

        $revision = DB::transaction(function () use ($revision, $approver): SampleRevision {
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

        $this->telegram->notifySampleRevisionApproved($revision);

        return $revision;
    }

    public function rejectRevision(SampleRevision $revision, ?string $reason = null): SampleRevision
    {
        $revision = DB::transaction(function () use ($revision, $reason): SampleRevision {
            $revision->update([
                'status' => SampleStatus::REJECTED,
                'notes' => $reason ?? $revision->notes,
            ]);

            $revision->sample->update(['status' => SampleStatus::REJECTED]);

            return $revision->refresh();
        });

        $this->telegram->notifySampleRevisionRejected($revision);

        return $revision;
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

            $order = Order::create([
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

            app(OrderMaterialFlowService::class)->initializeRequirements($order);

            return $order;
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
