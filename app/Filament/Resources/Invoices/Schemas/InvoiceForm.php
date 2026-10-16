<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Services\Finance\InvoiceService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * Formulir invoice klien.
 *
 * Alur kalkulasi auto:
 *   1. Pilih Klien  → reset semua
 *   2. Pilih Order  → hitung Ongkos Jahit + Overhead (conditional work_scheme)
 *   3. Isi PPN (%)  → recalculate PPN & grand total
 *   4. `amount` readonly — selalu = sewing + overhead + tax
 *
 * Field tersembunyi `_sewing_amount` dan `_overhead_amount` menyimpan
 * nilai antara agar recalculate PPN tidak perlu query ulang ke DB.
 *
 * PPN hanya bisa diisi oleh Finance (FINANCE_PAYROLL).
 */
class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                // ── Identitas Invoice ────────────────────────────────────
                TextInput::make('invoice_number')
                    ->label('Nomor Invoice')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->columnSpan(1),

                Select::make('client_id')
                    ->label('Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->columnSpan(1)
                    ->afterStateUpdated(function (callable $set): void {
                        $set('order_id', null);
                        $set('_sewing_amount', 0);
                        $set('_overhead_amount', 0);
                        $set('tax_percent', null);
                        $set('tax_amount', null);
                        $set('overhead_amount', null);
                        $set('amount', null);
                    }),

                // ── Pilih Order (difilter per klien) ─────────────────────
                Select::make('order_id')
                    ->label('Order')
                    ->placeholder('— Pilih order untuk kalkulasi otomatis —')
                    ->live()
                    ->columnSpanFull()
                    ->options(function (callable $get): array {
                        $clientId = $get('client_id');

                        if (! $clientId) {
                            return [];
                        }

                        return Order::query()
                            ->where('client_id', $clientId)
                            ->orderByDesc('created_at')
                            ->get()
                            ->mapWithKeys(fn (Order $order): array => [
                                $order->id => $order->invoiceSelectLabel(),
                            ])
                            ->all();
                    })
                    ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                        if (! $state) {
                            $set('_sewing_amount', 0);
                            $set('_overhead_amount', 0);
                            $set('overhead_amount', null);
                            $set('tax_percent', null);
                            $set('tax_amount', null);
                            $set('amount', null);

                            return;
                        }

                        $order = Order::find($state);
                        $tax = (float) ($get('tax_percent') ?? 0);

                        if (! $order) {
                            return;
                        }

                        $result = app(InvoiceService::class)->calculateAmountForOrder($order, $tax);

                        $set('_sewing_amount', $result['sewing_amount']);
                        $set('_overhead_amount', $result['overhead_amount']);
                        $set('overhead_amount', $result['overhead_amount'] > 0 ? $result['overhead_amount'] : null);
                        $set('tax_amount', $result['tax_amount'] > 0 ? $result['tax_amount'] : null);
                        $set('amount', $result['amount'] > 0 ? $result['amount'] : null);
                    }),

                // ── Preview breakdown kalkulasi ──────────────────────────
                Placeholder::make('_invoice_breakdown')
                    ->label('Rincian Kalkulasi')
                    ->content(function (callable $get): HtmlString {
                        $orderId = $get('order_id');

                        if (! $orderId) {
                            return new HtmlString('');
                        }

                        $order = Order::find($orderId);

                        if (! $order) {
                            return new HtmlString('');
                        }

                        $tax = (float) ($get('tax_percent') ?? 0);
                        $result = app(InvoiceService::class)->calculateAmountForOrder($order, $tax);

                        return new HtmlString(self::buildPreviewHtml($result));
                    })
                    ->visible(fn (callable $get): bool => (bool) $get('order_id'))
                    ->columnSpanFull(),

                // ── PPN (hanya Finance) ──────────────────────────────────
                TextInput::make('tax_percent')
                    ->label('PPN (%)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%')
                    ->placeholder('0')
                    ->helperText('Kosongkan atau isi 0 jika tidak ada pajak.')
                    ->live(onBlur: true)
                    ->disabled(fn (): bool => ! self::userIsFinance())
                    ->dehydrated(true)
                    ->columnSpan(1)
                    ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                        $sewing = (float) ($get('_sewing_amount') ?? 0);
                        $overhead = (float) ($get('_overhead_amount') ?? 0);
                        $tax = (float) ($state ?? 0);

                        $recalc = app(InvoiceService::class)->recalculateWithTax($sewing, $overhead, $tax);

                        $set('tax_amount', $recalc['tax_amount'] > 0 ? $recalc['tax_amount'] : null);
                        $set('amount', $recalc['amount'] > 0 ? $recalc['amount'] : null);
                    }),

                // ── Grand Total (readonly, auto) ──────────────────────────
                TextInput::make('amount')
                    ->label('Total Tagihan')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('Rp')
                    ->required()
                    ->readOnly()
                    ->columnSpan(1)
                    ->helperText(fn (callable $get): string => $get('order_id')
                        ? 'Dihitung otomatis: ongkos jahit + PPN (overhead hanya dicatat internal, tidak ditagihkan ke klien).'
                        : 'Pilih order di atas untuk kalkulasi otomatis.'),

                // ── Kolom tersembunyi (state antara) ─────────────────────
                TextInput::make('_sewing_amount')
                    ->hidden()
                    ->dehydrated(false),

                TextInput::make('_overhead_amount')
                    ->hidden()
                    ->dehydrated(false),

                // ── Kolom disimpan ke DB tapi tidak ditampilkan di form ──
                TextInput::make('overhead_amount')
                    ->hidden()
                    ->dehydrated(true),

                TextInput::make('tax_amount')
                    ->hidden()
                    ->dehydrated(true),

                // ── Tanggal & Catatan ─────────────────────────────────────
                DatePicker::make('issued_at')
                    ->label('Tanggal Terbit')
                    ->helperText('Kosongkan agar diisi otomatis saat diterbitkan.')
                    ->columnSpan(1),

                DatePicker::make('due_at')
                    ->label('Jatuh Tempo')
                    ->columnSpan(1),

                Textarea::make('notes')
                    ->label('Catatan')
                    ->columnSpanFull(),
            ]);
    }

    private static function userIsFinance(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return (bool) $user?->hasRoleSatisfying(
            fn (UserRole $role): bool => $role->canManageInvoices(),
        );
    }

    /**
     * Bangun HTML preview kalkulasi di form (compact, cukup untuk preview).
     *
     * @param  array{sewing_amount: float, overhead_amount: float, tax_amount: float, amount: float, is_overhead_applicable: bool, overhead_per_unit: float, overhead_qty: int, overhead_period: string|null, work_scheme: string, breakdown: array<int, array{component: string, qty: int, rate: float, subtotal: float}>, unrated_entries: int}  $result
     */
    private static function buildPreviewHtml(array $result): string
    {
        $rows = '';

        foreach ($result['breakdown'] as $line) {
            $rows .= sprintf(
                '<tr>
                    <td class="py-1 pr-3 text-sm text-gray-700">%s</td>
                    <td class="py-1 pr-3 text-sm text-right text-gray-500">%s pcs</td>
                    <td class="py-1 pr-3 text-sm text-right text-gray-500">Rp %s</td>
                    <td class="py-1 text-sm text-right font-medium text-gray-900">Rp %s</td>
                </tr>',
                htmlspecialchars($line['component']),
                number_format($line['qty']),
                number_format($line['rate'], 0, ',', '.'),
                number_format($line['subtotal'], 0, ',', '.'),
            );
        }

        // Subtotal ongkos jahit
        $summary = sprintf(
            '<tr class="border-t border-gray-200">
                <td colspan="3" class="pt-2 pr-3 text-sm font-medium text-gray-700">Subtotal Ongkos Jahit</td>
                <td class="pt-2 text-sm text-right font-semibold text-gray-900">Rp %s</td>
            </tr>',
            number_format($result['sewing_amount'], 0, ',', '.'),
        );

        // PPN
        if ($result['tax_amount'] > 0) {
            $summary .= sprintf(
                '<tr>
                    <td colspan="3" class="py-1 pr-3 text-sm text-orange-700">PPN</td>
                    <td class="py-1 text-sm text-right font-semibold text-orange-700">Rp %s</td>
                </tr>',
                number_format($result['tax_amount'], 0, ',', '.'),
            );
        }

        // Grand total tagihan klien
        $summary .= sprintf(
            '<tr class="border-t-2 border-primary-200">
                <td colspan="3" class="pt-2 pr-3 text-sm font-bold text-primary-700">Total Tagihan Klien</td>
                <td class="pt-2 text-sm text-right font-bold text-primary-700">Rp %s</td>
            </tr>',
            number_format($result['amount'], 0, ',', '.'),
        );

        // Overhead (catatan internal pabrik saja)
        $overheadNote = '';
        if ($result['is_overhead_applicable'] && $result['overhead_amount'] > 0) {
            $overheadNote = sprintf(
                '<div class="mt-3 p-2.5 bg-blue-50/60 rounded border border-dashed border-blue-200 text-xs text-blue-900">
                    <span class="font-bold">📋 Catatan Internal Pabrik (Overhead):</span>
                    %s — %s pcs × Rp %s/unit = <strong>Rp %s</strong> (tidak ditagihkan ke klien).
                </div>',
                htmlspecialchars($result['overhead_period'] ?? ''),
                number_format($result['overhead_qty']),
                number_format($result['overhead_per_unit'], 0, ',', '.'),
                number_format($result['overhead_amount'], 0, ',', '.'),
            );
        } elseif ($result['is_overhead_applicable'] && $result['overhead_amount'] === 0.0) {
            $overheadNote = '<div class="mt-2 text-xs text-amber-600 italic">
                ⚠ Overhead berlaku untuk skema ini tapi belum ada alokasi overhead bulan ini.
            </div>';
        }

        $warning = $result['unrated_entries'] > 0
            ? sprintf(
                '<p class="mt-2 text-xs text-amber-600">⚠ %d komponen tanpa rate card aktif dikecualikan dari kalkulasi.</p>',
                $result['unrated_entries'],
            )
            : '';

        return sprintf(
            '<div class="rounded-lg border border-gray-200 p-3 bg-gray-50">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                    Kalkulasi Tagihan Klien — Skema: %s
                </p>
                <table class="w-full">
                    <thead>
                        <tr>
                            <th class="pb-1 pr-3 text-xs text-left text-gray-400">Komponen</th>
                            <th class="pb-1 pr-3 text-xs text-right text-gray-400">Qty</th>
                            <th class="pb-1 pr-3 text-xs text-right text-gray-400">Rate</th>
                            <th class="pb-1 text-xs text-right text-gray-400">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>%s%s</tbody>
                </table>%s%s
            </div>',
            htmlspecialchars($result['work_scheme']),
            $rows,
            $summary,
            $overheadNote,
            $warning,
        );
    }
}
