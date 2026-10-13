<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Enums\InvoiceStatus;
use App\Enums\OutputStatus;
use App\Models\Invoice;
use App\Models\OutputEntry;
use App\Models\RateCard;
use App\Services\Finance\InvoiceService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/**
 * Daftar invoice klien dengan aksi terbit, tandai lunas, dan lihat detail
 * (matriks RBAC SRS, Finance).
 */
class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Nomor Invoice')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('client.name')
                    ->label('Klien')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('amount')
                    ->label('Nilai')
                    ->money('IDR')
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('issued_at')
                    ->label('Terbit')
                    ->date('d/m/Y')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('due_at')
                    ->label('Jatuh Tempo')
                    ->date('d/m/Y')
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(InvoiceStatus::class),
                SelectFilter::make('client_id')
                    ->label('Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('detail')
                    ->label('Detail')
                    ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
                    ->color('gray')
                    ->modalHeading(fn (Invoice $record): string => $record->invoice_number)
                    ->modalDescription(null)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (Invoice $record): HtmlString => new HtmlString(
                        self::buildDetailHtml($record)
                    ))
                    ->modalWidth('4xl'),

                Action::make('issue')
                    ->label('Terbitkan')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Invoice $record): bool => (bool) auth()->user()?->can('issue', $record))
                    ->action(fn (Invoice $record) => app(InvoiceService::class)->issue($record))
                    ->successNotificationTitle('Invoice diterbitkan'),

                Action::make('pay')
                    ->label('Tandai Lunas')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Invoice $record): bool => (bool) auth()->user()?->can('pay', $record))
                    ->action(fn (Invoice $record) => app(InvoiceService::class)->markPaid($record))
                    ->successNotificationTitle('Invoice ditandai lunas'),

                EditAction::make()
                    ->label('Ubah'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    // -------------------------------------------------------------------------
    // Modal Detail HTML
    // -------------------------------------------------------------------------

    private static function buildDetailHtml(Invoice $record): string
    {
        [$statusBg, $statusText, $statusDot] = match ($record->status) {
            InvoiceStatus::DRAFT => ['#f3f4f6', '#374151', '#9ca3af'],
            InvoiceStatus::ISSUED => ['#fffbeb', '#92400e', '#f59e0b'],
            InvoiceStatus::PAID => ['#f0fdf4', '#14532d', '#22c55e'],
            default => ['#f3f4f6', '#374151', '#9ca3af'],
        };

        $statusLabel = $record->status->getLabel();
        $clientName = htmlspecialchars($record->client?->name ?? '-');
        $orderNumber = $record->order
            ? "<strong>{$record->order->order_number}</strong>"
            : '<span style="color:#9ca3af;font-style:italic">Tanpa order</span>';
        $amountFmt = 'Rp '.number_format((float) $record->amount, 0, ',', '.');
        $issuedAt = $record->issued_at ? $record->issued_at->format('d M Y') : '-';
        $dueAt = $record->due_at ? $record->due_at->format('d M Y') : '-';
        $issuerName = htmlspecialchars($record->issuer?->name ?? '-');
        $createdAt = $record->created_at->format('d M Y, H:i');

        // ── Status badge + judul ───────────────────────────────────────────
        $hero = sprintf(
            '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
                <div>
                    <p style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#6b7280;margin:0 0 4px">Nomor Invoice</p>
                    <p style="font-size:20px;font-weight:700;color:#111827;margin:0">%s</p>
                </div>
                <div style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:9999px;background:%s">
                    <span style="width:8px;height:8px;border-radius:50%%;background:%s;flex-shrink:0"></span>
                    <span style="font-size:13px;font-weight:600;color:%s">%s</span>
                </div>
            </div>',
            htmlspecialchars($record->invoice_number),
            $statusBg, $statusDot, $statusText, $statusLabel,
        );

        // ── Kartu info utama (2 kolom) ─────────────────────────────────────
        $notesRow = $record->notes
            ? sprintf(
                '<div style="grid-column:1/-1;padding-top:12px;border-top:1px solid #e5e7eb">
                    <p style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#9ca3af;margin:0 0 4px">Catatan</p>
                    <p style="font-size:13px;color:#374151;margin:0">%s</p>
                </div>',
                htmlspecialchars($record->notes),
            )
            : '';

        $infoCard = sprintf(
            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:16px;margin-bottom:20px">
                %s%s%s%s%s%s%s%s%s
            </div>',
            self::infoCell('Klien', $clientName),
            self::infoCell('Order', $orderNumber),
            self::infoCell('Skema', $record->order ? htmlspecialchars($record->order->work_scheme->getLabel()) : '-'),
            self::infoCell('Total Tagihan', '<span style="font-size:16px;font-weight:700;color:#1d4ed8">'.$amountFmt.'</span>'),
            self::infoCell('Diterbitkan oleh', $issuerName),
            self::infoCell('Tanggal Terbit', $issuedAt),
            self::infoCell('Jatuh Tempo', '<span style="'.($record->due_at && $record->due_at->isPast() && $record->status !== InvoiceStatus::PAID ? 'color:#dc2626;font-weight:600' : 'color:#111827').'">'.$dueAt.'</span>'),
            $record->tax_percent
                ? self::infoCell('PPN', number_format((float) $record->tax_percent, 2).'%')
                : '',
            $notesRow,
        );

        // ── Breakdown tabel ────────────────────────────────────────────────
        $breakdownSection = self::buildBreakdownSection($record);

        // ── Footer timestamps ──────────────────────────────────────────────
        $footer = sprintf(
            '<p style="font-size:11px;color:#9ca3af;text-align:right;margin-top:16px">Dibuat: %s</p>',
            $createdAt,
        );

        return $hero.$infoCard.$breakdownSection.$footer;
    }

    /** Satu sel info — label kecil abu + nilai di bawahnya */
    private static function infoCell(string $label, string $value): string
    {
        return sprintf(
            '<div>
                <p style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#9ca3af;margin:0 0 3px">%s</p>
                <p style="font-size:13px;color:#111827;margin:0">%s</p>
            </div>',
            $label,
            $value,
        );
    }

    // -------------------------------------------------------------------------
    // Breakdown tabel ongkos jahit
    // -------------------------------------------------------------------------

    private static function buildBreakdownSection(Invoice $record): string
    {
        if (! $record->order_id) {
            return self::emptyState(
                '📋',
                'Invoice tanpa order',
                'Invoice ini tidak ditautkan ke order produksi, sehingga rincian komponen tidak tersedia.',
            );
        }

        $today = now()->toDateString();

        $verifiedEntries = OutputEntry::query()
            ->where('order_id', $record->order_id)
            ->where('status', OutputStatus::VERIFIED)
            ->with('component')
            ->get();

        if ($verifiedEntries->isEmpty()) {
            return self::emptyState(
                '🔍',
                'Belum ada output terverifikasi',
                'Mandor belum memverifikasi output untuk order ini, atau semua output ditolak.',
            );
        }

        $componentIds = $verifiedEntries->pluck('component_id')->unique()->filter();

        $rateCards = RateCard::query()
            ->where('client_id', $record->client_id)
            ->whereIn('component_id', $componentIds)
            ->effectiveOn($today)
            ->get()
            ->keyBy('component_id');

        $lines = [];
        $grandTotal = 0.0;
        $unratedCount = 0;

        foreach ($verifiedEntries as $entry) {
            $rateCard = $rateCards->get($entry->component_id);
            $componentName = $entry->component?->name ?? "Komponen #{$entry->component_id}";

            if ($rateCard === null) {
                $unratedCount++;
                $lines[$entry->component_id] ??= [
                    'component' => $componentName,
                    'qty' => 0,
                    'rate' => null,
                    'subtotal' => null,
                    'no_rate' => true,
                ];
                $lines[$entry->component_id]['qty'] += $entry->quantity;

                continue;
            }

            $subtotal = $entry->quantity * (float) $rateCard->rate;
            $grandTotal += $subtotal;

            $lines[$entry->component_id] ??= [
                'component' => $componentName,
                'qty' => 0,
                'rate' => (float) $rateCard->rate,
                'subtotal' => 0.0,
                'no_rate' => false,
            ];
            $lines[$entry->component_id]['qty'] += $entry->quantity;
            $lines[$entry->component_id]['subtotal'] += $subtotal;
        }

        // ── Baris tabel ────────────────────────────────────────────────────
        $rows = '';
        $idx = 0;

        foreach ($lines as $line) {
            $rowBg = $idx % 2 === 0 ? '#ffffff' : '#f9fafb';
            $idx++;

            if ($line['no_rate']) {
                $rows .= sprintf(
                    '<tr style="background:%s">
                        <td style="padding:10px 12px;font-size:13px;color:#374151;border-bottom:1px solid #f3f4f6">%s</td>
                        <td style="padding:10px 12px;font-size:13px;text-align:right;color:#374151;border-bottom:1px solid #f3f4f6">%s pcs</td>
                        <td style="padding:10px 12px;font-size:12px;text-align:right;color:#d97706;font-style:italic;border-bottom:1px solid #f3f4f6">⚠ Tanpa rate</td>
                        <td style="padding:10px 12px;font-size:13px;text-align:right;color:#d97706;font-style:italic;border-bottom:1px solid #f3f4f6">-</td>
                    </tr>',
                    $rowBg,
                    htmlspecialchars($line['component']),
                    number_format($line['qty']),
                );
            } else {
                $rows .= sprintf(
                    '<tr style="background:%s">
                        <td style="padding:10px 12px;font-size:13px;color:#374151;border-bottom:1px solid #f3f4f6">%s</td>
                        <td style="padding:10px 12px;font-size:13px;text-align:right;color:#374151;border-bottom:1px solid #f3f4f6">%s pcs</td>
                        <td style="padding:10px 12px;font-size:13px;text-align:right;color:#6b7280;border-bottom:1px solid #f3f4f6">Rp %s</td>
                        <td style="padding:10px 12px;font-size:13px;text-align:right;font-weight:600;color:#111827;border-bottom:1px solid #f3f4f6">Rp %s</td>
                    </tr>',
                    $rowBg,
                    htmlspecialchars($line['component']),
                    number_format($line['qty']),
                    number_format($line['rate'], 0, ',', '.'),
                    number_format($line['subtotal'], 0, ',', '.'),
                );
            }
        }

        // ── Baris total kalkulasi ───────────────────────────────────────────
        $rows .= sprintf(
            '<tr style="background:#eff6ff;border-top:2px solid #bfdbfe">
                <td colspan="3" style="padding:11px 12px;font-size:13px;font-weight:600;color:#1e40af">Subtotal Ongkos Jahit</td>
                <td style="padding:11px 12px;font-size:14px;font-weight:700;text-align:right;color:#1e40af">Rp %s</td>
            </tr>',
            number_format($grandTotal, 0, ',', '.'),
        );

        // ── Overhead (jika ada di record) ───────────────────────────────────
        $overheadAmount = (float) ($record->overhead_amount ?? 0);

        if ($overheadAmount > 0) {
            $rows .= sprintf(
                '<tr style="background:#eef2ff">
                    <td colspan="3" style="padding:10px 12px;font-size:13px;color:#4338ca">
                        🏭 Biaya Overhead
                    </td>
                    <td style="padding:10px 12px;font-size:13px;text-align:right;font-weight:600;color:#4338ca">Rp %s</td>
                </tr>',
                number_format($overheadAmount, 0, ',', '.'),
            );
        }

        // ── Subtotal sebelum pajak ──────────────────────────────────────────
        $subtotalBeforeTax = $grandTotal + $overheadAmount;

        if ($overheadAmount > 0) {
            $rows .= sprintf(
                '<tr style="background:#f5f3ff">
                    <td colspan="3" style="padding:9px 12px;font-size:13px;font-weight:600;color:#5b21b6">Subtotal (Jahit + Overhead)</td>
                    <td style="padding:9px 12px;font-size:13px;text-align:right;font-weight:700;color:#5b21b6">Rp %s</td>
                </tr>',
                number_format($subtotalBeforeTax, 0, ',', '.'),
            );
        }

        // ── PPN ─────────────────────────────────────────────────────────────
        $taxAmount = (float) ($record->tax_amount ?? 0);
        $taxPercent = (float) ($record->tax_percent ?? 0);

        if ($taxAmount > 0) {
            $rows .= sprintf(
                '<tr style="background:#fff7ed">
                    <td colspan="3" style="padding:10px 12px;font-size:13px;color:#c2410c">
                        🧾 PPN %.2f%%
                    </td>
                    <td style="padding:10px 12px;font-size:13px;text-align:right;font-weight:600;color:#c2410c">Rp %s</td>
                </tr>',
                $taxPercent,
                number_format($taxAmount, 0, ',', '.'),
            );
        }

        // ── Baris penyesuaian jika amount invoice ≠ kalkulasi ──────────────
        $expectedTotal = round($subtotalBeforeTax + $taxAmount, 2);
        $diff = round((float) $record->amount - $expectedTotal, 2);

        if (abs($diff) > 0.01) {
            [$adjColor, $adjSign] = $diff > 0
                ? ['#15803d', '+']
                : ['#dc2626', '-'];

            $rows .= sprintf(
                '<tr style="background:#fafafa">
                    <td colspan="3" style="padding:8px 12px;font-size:12px;color:#6b7280;font-style:italic">Penyesuaian manual</td>
                    <td style="padding:8px 12px;font-size:12px;text-align:right;font-style:italic;color:%s">%s Rp %s</td>
                </tr>',
                $adjColor,
                $adjSign,
                number_format(abs($diff), 0, ',', '.'),
            );
        }

        // ── Baris total invoice (final) ─────────────────────────────────────
        $rows .= sprintf(
            '<tr style="background:#1d4ed8">
                <td colspan="3" style="padding:13px 12px;font-size:14px;font-weight:700;color:#ffffff">Total Invoice</td>
                <td style="padding:13px 12px;font-size:15px;font-weight:800;text-align:right;color:#ffffff">Rp %s</td>
            </tr>',
            number_format((float) $record->amount, 0, ',', '.'),
        );

        // ── Warning rate kosong ─────────────────────────────────────────────
        $warning = '';

        if ($unratedCount > 0) {
            $warning = sprintf(
                '<div style="display:flex;align-items:flex-start;gap:8px;margin-top:12px;padding:10px 12px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px">
                    <span style="font-size:14px;flex-shrink:0">⚠️</span>
                    <p style="margin:0;font-size:12px;color:#92400e"><strong>%d komponen</strong> tidak memiliki rate card aktif dan dikecualikan dari kalkulasi. Hubungi Finance untuk menetapkan tarif.</p>
                </div>',
                $unratedCount,
            );
        }

        return sprintf(
            '<div style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;margin-bottom:4px">
                <div style="padding:12px 16px;background:#f1f5f9;border-bottom:1px solid #e5e7eb">
                    <p style="margin:0;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#475569">📦 Rincian Ongkos Jahit — %s</p>
                </div>
                <div style="overflow-x:auto">
                    <table style="width:100%%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#f8fafc">
                                <th style="padding:10px 12px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;text-align:left;border-bottom:2px solid #e5e7eb">Komponen</th>
                                <th style="padding:10px 12px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;text-align:right;border-bottom:2px solid #e5e7eb">Qty</th>
                                <th style="padding:10px 12px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;text-align:right;border-bottom:2px solid #e5e7eb">Rate/pcs</th>
                                <th style="padding:10px 12px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;text-align:right;border-bottom:2px solid #e5e7eb">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>%s</tbody>
                    </table>
                </div>
            </div>%s',
            htmlspecialchars($record->order?->order_number ?? 'Manual'),
            $rows,
            $warning,
        );
    }

    /** Kotak kosong dengan ikon dan keterangan */
    private static function emptyState(string $icon, string $title, string $desc): string
    {
        return sprintf(
            '<div style="text-align:center;padding:28px 16px;background:#f9fafb;border:1px dashed #d1d5db;border-radius:10px">
                <div style="font-size:28px;margin-bottom:8px">%s</div>
                <p style="margin:0 0 4px;font-size:14px;font-weight:600;color:#374151">%s</p>
                <p style="margin:0;font-size:12px;color:#9ca3af">%s</p>
            </div>',
            $icon,
            $title,
            $desc,
        );
    }
}
