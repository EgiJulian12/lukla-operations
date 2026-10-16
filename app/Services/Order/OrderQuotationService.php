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
        WorkScheme $workScheme = WorkScheme::CMT,
        mixed $dueDate = null,
        ?int $quantity = null,
        mixed $startDate = null,
    ): Order {
        return Order::create([
            'client_id' => $client->getKey(),
            'article_id' => $article?->getKey(),
            'order_number' => $this->nextOrderNumber(),
            'work_scheme' => $workScheme,
            'quantity' => $quantity,
            'start_date' => $startDate,
            'due_date' => $dueDate,
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
        mixed $startDate = null,
        mixed $dueDate = null,
    ): Order {
        if (! $order->status->needsQuotation()) {
            throw InvalidOrderQuotationState::notAwaitingQuotation($order);
        }

        return DB::transaction(function () use ($order, $estimatedCost, $quoteNotes, $owner, $startDate, $dueDate): Order {
            $updateData = [
                'estimated_cost' => $estimatedCost,
                'quote_notes' => $quoteNotes,
                'quoted_at' => now(),
                'quoted_by' => $owner?->getKey() ?? auth()->id(),
                'status' => OrderStatus::QUOTED,
            ];

            if ($startDate !== null) {
                $updateData['start_date'] = $startDate;
            }

            if ($dueDate !== null) {
                $updateData['due_date'] = $dueDate;
            }

            $order->update($updateData);

            $this->audit($order, 'quotation_provided', [
                'after' => [
                    'estimated_cost' => $estimatedCost,
                    'quote_notes' => $quoteNotes,
                    'start_date' => $order->start_date,
                    'due_date' => $order->due_date,
                ],
            ], $owner);

            return $order->refresh();
        });
    }

    /**
     * Klien menyetujui penawaran, membuka langkah permintaan sample.
     */
    public function approveQuote(
        Order $order,
        ?User $client = null,
        mixed $dueDate = null,
        ?WorkScheme $workScheme = null,
        ?string $notes = null,
        ?int $quantity = null,
    ): Order {
        if (! $order->status->awaitingClientApproval()) {
            throw InvalidOrderQuotationState::notAwaitingClientApproval($order);
        }

        return DB::transaction(function () use ($order, $client, $dueDate, $workScheme, $notes, $quantity): Order {
            $updateData = [
                'quote_approved_at' => now(),
                'status' => OrderStatus::QUOTE_APPROVED,
            ];

            if ($quantity !== null && $quantity > 0) {
                $updateData['quantity'] = $quantity;
            }

            if ($dueDate !== null) {
                $updateData['due_date'] = $dueDate;
            }

            if ($workScheme !== null) {
                $updateData['work_scheme'] = $workScheme;
            }

            if ($notes !== null && trim($notes) !== '') {
                $updateData['notes'] = $notes;
            }

            $order->update($updateData);

            $this->audit($order, 'quotation_approved', [
                'after' => [
                    'estimated_cost' => $order->estimated_cost,
                    'quantity' => $order->quantity,
                    'due_date' => $order->due_date,
                    'work_scheme' => $order->work_scheme?->value,
                    'notes' => $order->notes,
                ],
            ], $client);

            return $order->refresh();
        });
    }

    /**
     * Klien menolak penawaran; pesanan langsung ditutup (CANCELLED) secara permanen.
     */
    public function rejectQuote(Order $order, ?User $client = null, ?string $reason = null): Order
    {
        if (! $order->status->awaitingClientApproval()) {
            throw InvalidOrderQuotationState::notAwaitingClientApproval($order);
        }

        return DB::transaction(function () use ($order, $client, $reason): Order {
            $order->update([
                'status' => OrderStatus::CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $client?->getKey() ?? auth()->id(),
                'cancellation_reason' => $reason,
            ]);

            $this->audit($order, 'quotation_rejected', [
                'reason' => $reason,
                'cancelled_by' => $client?->getKey() ?? auth()->id(),
            ], $client);

            return $order->refresh();
        });
    }

    /**
     * Owner menolak permintaan order klien; pesanan langsung ditutup (CANCELLED) secara permanen.
     */
    public function rejectRequest(Order $order, ?User $owner = null, ?string $reason = null): Order
    {
        if (! in_array($order->status, [OrderStatus::REQUESTED, OrderStatus::QUOTED], true)) {
            throw new \InvalidArgumentException('Hanya pesanan yang masih dalam tahap permintaan atau penawaran yang dapat ditolak.');
        }

        return DB::transaction(function () use ($order, $owner, $reason): Order {
            $order->update([
                'status' => OrderStatus::CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $owner?->getKey() ?? auth()->id(),
                'cancellation_reason' => $reason,
            ]);

            $this->audit($order, 'request_rejected', [
                'reason' => $reason,
                'cancelled_by' => $owner?->getKey() ?? auth()->id(),
            ], $owner);

            return $order->refresh();
        });
    }

    /**
     * Menandai permintaan sample ke Tim Sample untuk order yang telah disetujui.
     */
    public function requestSample(Order $order, ?User $owner = null): Order
    {
        if (! $order->status->isQuoteApproved()) {
            throw InvalidOrderQuotationState::notQuoteApproved($order);
        }

        if ($order->sample_requested_at !== null) {
            throw InvalidOrderQuotationState::sampleAlreadyRequested($order);
        }

        return DB::transaction(function () use ($order, $owner): Order {
            $order->update([
                'sample_requested_at' => now(),
            ]);

            $this->audit($order, 'sample_requested', [
                'after' => ['sample_requested_at' => now()->toDateTimeString()],
            ], $owner);

            return $order->refresh();
        });
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
