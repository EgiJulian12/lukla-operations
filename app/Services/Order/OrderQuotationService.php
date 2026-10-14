<?php

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Enums\WorkScheme;
use App\Exceptions\InvalidOrderQuotationState;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Order;
use App\Models\Sample;
use App\Models\User;
use App\Services\Sample\SampleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Jalur tulis untuk fase pra-produksi order: permintaan klien, penawaran owner,
 * persetujuan klien, dan permintaan sample.
 *
 * Satu order membawa seluruh rantai ini (bukan entitas terpisah per tahap),
 * sehingga riwayat dari gambar design klien sampai sample yang dikerjakan tetap
 * dapat ditelusuri pada satu nomor order.
 */
class OrderQuotationService
{
    public function __construct(private readonly SampleService $samples) {}

    /**
     * Permintaan klien: gambar design dan deskripsi tanpa jumlah unit.
     *
     * Pemilik order diambil dari parameter Client yang sudah terautentikasi,
     * bukan dari input pemanggil, sehingga klien tidak dapat membuat order atas
     * nama klien lain (FR-025).
     *
     * @param  array<int, string>  $designPaths
     */
    public function submitRequest(
        Client $client,
        array $designPaths,
        string $description,
        ?Article $article = null,
        ?User $requester = null,
    ): Order {
        return Order::create([
            'client_id' => $client->getKey(),
            'article_id' => $article?->getKey(),
            'order_number' => $this->nextOrderNumber(),
            'work_scheme' => WorkScheme::CMT,
            'description' => $description,
            'design_images' => $designPaths,
            'status' => OrderStatus::REQUESTED,
            'created_by' => $requester?->getKey() ?? auth()->id(),
        ]);
    }

    /**
     * Penawaran owner atas permintaan klien.
     */
    public function provideQuote(
        Order $order,
        float $estimatedCost,
        ?string $quoteNotes,
        ?User $owner = null,
    ): Order {
        if (! $order->status->needsQuotation()) {
            throw InvalidOrderQuotationState::notAwaitingQuotation($order);
        }

        return DB::transaction(function () use ($order, $estimatedCost, $quoteNotes, $owner): Order {
            $order->update([
                'estimated_cost' => $estimatedCost,
                'quote_notes' => $quoteNotes,
                'quoted_at' => now(),
                'quoted_by' => $owner?->getKey() ?? auth()->id(),
                'status' => OrderStatus::QUOTED,
            ]);

            $this->audit($order, 'quotation_provided', [
                'after' => ['estimated_cost' => $estimatedCost, 'quote_notes' => $quoteNotes],
            ], $owner);

            return $order->refresh();
        });
    }

    /**
     * Klien menyetujui penawaran, membuka langkah permintaan sample.
     */
    public function approveQuote(Order $order, ?User $client = null): Order
    {
        if (! $order->status->awaitingClientApproval()) {
            throw InvalidOrderQuotationState::notAwaitingClientApproval($order);
        }

        return DB::transaction(function () use ($order, $client): Order {
            $order->update([
                'quote_approved_at' => now(),
                'status' => OrderStatus::QUOTE_APPROVED,
            ]);

            $this->audit($order, 'quotation_approved', [
                'after' => ['estimated_cost' => $order->estimated_cost],
            ], $client);

            return $order->refresh();
        });
    }

    /**
     * Klien menolak penawaran; order kembali ke antrean owner untuk ditawar ulang.
     */
    public function rejectQuote(Order $order, ?string $reason = null): Order
    {
        if (! $order->status->awaitingClientApproval()) {
            throw InvalidOrderQuotationState::notAwaitingClientApproval($order);
        }

        $notes = trim(implode("\n", array_filter([$order->quote_notes, $reason])));

        return DB::transaction(function () use ($order, $notes): Order {
            $order->update([
                'status' => OrderStatus::REQUESTED,
                'quote_notes' => $notes !== '' ? $notes : null,
            ]);

            $this->audit($order, 'quotation_rejected', ['reason' => $notes]);

            return $order->refresh();
        });
    }

    /**
     * Owner mengirim permintaan sample dan membuat sample nyata dari design
     * klien, sehingga alur sample → persetujuan → order massal dapat berlanjut
     * (FR-001..004).
     */
    public function requestSample(Order $order, ?User $owner = null): Sample
    {
        if (! $order->status->isQuoteApproved()) {
            throw InvalidOrderQuotationState::notQuoteApproved($order);
        }

        if ($order->sample_id !== null) {
            throw InvalidOrderQuotationState::sampleAlreadyRequested($order);
        }

        return DB::transaction(function () use ($order, $owner): Sample {
            $article = $order->article ?? $this->articleFromOrder($order);

            $sample = $this->samples->createSample(
                $article,
                $order->order_number,
                $this->sampleTitle($order),
                $order->description,
            );

            $this->samples->addRevision($sample, [
                'design_images' => $order->design_images ?? [],
                'description' => $order->description,
                'order_number' => $order->order_number,
            ]);

            $order->update([
                'article_id' => $article->getKey(),
                'sample_id' => $sample->getKey(),
                'sample_requested_at' => now(),
            ]);

            $this->audit($order, 'sample_requested', [
                'after' => ['sample_id' => $sample->getKey()],
            ], $owner);

            return $sample;
        });
    }

    /**
     * Artikel penampung saat klien tidak memilih artikel. Sample mewajibkan
     * article_id, sedangkan permintaan klien hanya membawa deskripsi.
     */
    private function articleFromOrder(Order $order): Article
    {
        return Article::create([
            'client_id' => $order->client_id,
            'code' => $order->order_number,
            'name' => Str::limit(trim((string) $order->description), 120, '') ?: $order->order_number,
            'description' => $order->description,
        ]);
    }

    private function sampleTitle(Order $order): string
    {
        return Str::limit(trim((string) $order->description), 120, '') ?: 'Sample '.$order->order_number;
    }

    /**
     * Nomor permintaan unik lintas klien. Pencarian sisa nomor sengaja melepas
     * global scope klien; dengan scope aktif, dua klien dapat menghitung sisa
     * yang sama dan menghasilkan nomor kembar.
     */
    private function nextOrderNumber(): string
    {
        $prefix = 'REQ-'.now()->format('Ym').'-';

        $used = Order::withTrashed()
            ->withoutGlobalScope('client')
            ->where('order_number', 'like', $prefix.'%')
            ->count();

        return $prefix.str_pad((string) ($used + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Kejadian domain pra-produksi dicatat eksplisit karena bukan sekadar
     * perubahan kolom (FR-022, FR-026).
     *
     * @param  array{before?: array<string, mixed>|null, after?: array<string, mixed>|null, reason?: string|null}  $meta
     */
    private function audit(Order $order, string $event, array $meta = [], ?User $actor = null): void
    {
        AuditLog::record([
            'user_id' => $actor?->getKey() ?? auth()->id(),
            'auditable_type' => $order->getMorphClass(),
            'auditable_id' => $order->getKey(),
            'event' => $event,
            'client_id' => $order->client_id,
            'order_id' => $order->getKey(),
            'before' => $meta['before'] ?? null,
            'after' => $meta['after'] ?? null,
            'reason' => $meta['reason'] ?? null,
        ]);
    }
}
