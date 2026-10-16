<?php

namespace App\Filament\Resources\PayrollPeriods\Tables;

use App\Enums\OutputStatus;
use App\Enums\PayrollPeriodStatus;
use App\Models\OutputEntry;
use App\Models\PayrollPeriod;
use App\Services\Payroll\PayrollService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/**
 * Daftar periode payroll dengan aksi hitung, setujui, dan tandai dibayar
 * (FR-015, FR-026).
 *
 * Setiap aksi hanya muncul pada keadaan yang tepat dan bagi pengguna yang
 * berwenang, sehingga urutan hitung, setujui, dan bayar tidak dapat dilompati.
 */
class PayrollPeriodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('period_start')
                    ->label('Awal')
                    ->date()
                    ->sortable(),
                TextColumn::make('period_end')
                    ->label('Akhir')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('meal_allowance_percent')
                    ->label('Uang Makan')
                    ->suffix('%')
                    ->placeholder('-'),
                TextColumn::make('qc_bonus_rate_per_unit')
                    ->label('Tarif Bonus QC')
                    ->money('IDR')
                    ->placeholder('-'),
                TextColumn::make('total_sewing_cost')
                    ->label('Total Ongkos Jahit')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('qcBonusPool.total_pool')
                    ->label('Pool Bonus QC')
                    ->money('IDR')
                    ->placeholder('-'),
                TextColumn::make('entries_count')
                    ->label('Penjahit')
                    ->counts('entries')
                    ->numeric(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(PayrollPeriodStatus::class),
            ])
            ->recordActions([
                Action::make('qcBonusDetails')
                    ->label('Bonus QC')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->color('gray')
                    ->modalHeading(fn (PayrollPeriod $record): string => 'Rincian Bonus QC — Periode '.$record->getLabelAttribute())
                    ->modalContent(fn (PayrollPeriod $record): HtmlString => new HtmlString(self::buildQcBonusDetailHtml($record)))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
                Action::make('calculate')
                    ->label('Hitung')
                    ->icon(Heroicon::OutlinedCalculator)
                    ->color('warning')
                    ->visible(fn (PayrollPeriod $record): bool => (bool) auth()->user()?->can('calculate', $record))
                    ->modalHeading('Hitung Payroll Periode Ini')
                    ->modalDescription(
                        'Menghitung upah borongan dari seluruh output terverifikasi dalam rentang periode ini. '
                        .'Bonus QC akan otomatis dibagikan ke 2 Mandor dan 1 Kepala Operasional. '
                        .'Hasil sebelumnya akan diganti.'
                    )
                    ->requiresConfirmation()
                    ->action(function (PayrollPeriod $record): void {
                        $period = app(PayrollService::class)->calculate($record);

                        $count = $period->entries()->count();

                        if ($count > 0) {
                            $totalSewingFmt = 'Rp '.number_format((float) $period->total_sewing_cost, 0, ',', '.');
                            Notification::make()
                                ->title('Payroll berhasil dihitung')
                                ->body("Ditemukan {$count} penjahit dengan total ongkos jahit {$totalSewingFmt}.")
                                ->success()
                                ->send();
                        } else {
                            $latestEntry = OutputEntry::withoutGlobalScope('client')
                                ->where('status', OutputStatus::VERIFIED->value)
                                ->latest('interval_start')
                                ->first();

                            $rangeStr = $record->period_start?->format('d/m/Y').' s/d '.$record->period_end?->format('d/m/Y');

                            if ($latestEntry) {
                                $entryDate = $latestEntry->interval_start?->format('d/m/Y') ?? '-';
                                $message = "Tidak ada output terverifikasi pada rentang {$rangeStr}. "
                                    ."Output penjahit terakhir tercatat pada tanggal {$entryDate}. "
                                    .'Silakan ubah tanggal periode agar mencakup tanggal pengerjaan penjahit.';
                            } else {
                                $message = "Tidak ada output terverifikasi pada rentang {$rangeStr}. "
                                    ."Pastikan penjahit telah melaporkan output dan mandor telah memverifikasinya di menu 'Verifikasi Output'.";
                            }

                            Notification::make()
                                ->title('Tidak ada data pada periode ini')
                                ->body($message)
                                ->warning()
                                ->persistent()
                                ->send();
                        }
                    }),
                Action::make('approve')
                    ->label('Setujui')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PayrollPeriod $record): bool => (bool) auth()->user()?->can('approve', $record))
                    ->action(fn (PayrollPeriod $record) => app(PayrollService::class)->approve($record))
                    ->successNotificationTitle('Payroll disetujui'),
                Action::make('pay')
                    ->label('Tandai Dibayar')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (PayrollPeriod $record): bool => (bool) auth()->user()?->can('pay', $record))
                    ->action(fn (PayrollPeriod $record) => app(PayrollService::class)->markPaid($record))
                    ->successNotificationTitle('Payroll ditandai dibayar'),
                EditAction::make()
                    ->label('Ubah'),
            ])
            ->defaultSort('period_start', 'desc');
    }

    /**
     * Tampilan modal rincian Bonus QC per artikel dan per penerima (2 Mandor + 1 Kepala Operasional).
     */
    private static function buildQcBonusDetailHtml(PayrollPeriod $record): string
    {
        $pool = $record->qcBonusPool;
        $breakdown = $record->getGroupedQcPassedBreakdown();
        $rate = (float) ($record->qc_bonus_rate_per_unit ?? 0);
        $totalPassed = (int) ($pool?->total_passed_units ?? 0);
        $totalPoolFmt = 'Rp '.number_format((float) ($pool?->total_pool ?? 0), 0, ',', '.');
        $rateFmt = 'Rp '.number_format($rate, 0, ',', '.');

        // ── Distribusi ke 2 Mandor + 1 Kepala Operasional ───────────────────
        $distributions = $pool?->distributions()->with('user.roles')->get() ?? collect();
        $distHtml = '';

        if ($distributions->isEmpty()) {
            $distHtml = '<div style="font-size:12px;color:#6b7280;font-style:italic">Belum dihitung. Klik tombol <strong>Hitung</strong> untuk memproses bonus ke 2 Mandor dan 1 Kepala Operasional.</div>';
        } else {
            $distCards = [];
            foreach ($distributions as $dist) {
                $roleLabel = $dist->user?->roles?->first()?->name ?? 'Penerima';
                $roleTitle = match ($roleLabel) {
                    'kepala_produksi' => 'Mandor Line',
                    'owner_eksekutif' => 'Kepala Operasional / Owner',
                    default => $roleLabel,
                };
                $userName = htmlspecialchars($dist->user?->name ?? '-');
                $amountFmt = 'Rp '.number_format((float) $dist->amount, 0, ',', '.');

                $distCards[] = sprintf(
                    '<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px">
                        <span style="font-size:10px;color:#166534;font-weight:700;text-transform:uppercase;letter-spacing:0.05em">%s</span>
                        <div style="font-size:13px;font-weight:700;color:#111827;margin-top:2px">%s</div>
                        <div style="font-size:14px;font-weight:800;color:#15803d;margin-top:4px">%s</div>
                    </div>',
                    htmlspecialchars($roleTitle),
                    $userName,
                    $amountFmt,
                );
            }
            $distHtml = '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px">'.implode('', $distCards).'</div>';
        }

        // ── Baris rincian per artikel / order / klien ────────────────────────
        $rowsHtml = '';
        if (empty($breakdown)) {
            $rowsHtml = '<tr><td colspan="5" style="padding:16px;text-align:center;color:#6b7280;font-style:italic">Belum ada unit lolos QC yang tercatat pada rentang periode ini.</td></tr>';
        } else {
            foreach ($breakdown as $row) {
                $client = htmlspecialchars($row['client_name']);
                $order = htmlspecialchars($row['order_number']);
                $article = htmlspecialchars($row['article_name']);
                $passed = (int) $row['total_passed'];
                $subtotal = round($passed * $rate, 2);
                $subtotalFmt = 'Rp '.number_format($subtotal, 0, ',', '.');

                $rowsHtml .= sprintf(
                    '<tr style="border-bottom:1px solid #f3f4f6">
                        <td style="padding:9px 12px;font-weight:600;color:#111827;font-size:13px">%s</td>
                        <td style="padding:9px 12px;color:#4b5563;font-size:13px">%s</td>
                        <td style="padding:9px 12px;color:#1e40af;font-weight:600;font-size:13px">📦 %s</td>
                        <td style="padding:9px 12px;text-align:right;color:#15803d;font-weight:700;font-size:13px">%s unit</td>
                        <td style="padding:9px 12px;text-align:right;color:#111827;font-weight:700;font-size:13px">%s</td>
                    </tr>',
                    $client,
                    $order,
                    $article,
                    number_format($passed),
                    $subtotalFmt,
                );
            }
        }

        return sprintf(
            '<div>
                <!-- Ringkasan Pool -->
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px">
                    <div style="background:#f9fafb;padding:12px;border-radius:8px;border:1px solid #e5e7eb">
                        <span style="font-size:10px;color:#6b7280;text-transform:uppercase;font-weight:600">Total Unit Lolos</span>
                        <div style="font-size:14px;font-weight:700;color:#111827;margin-top:2px">%s unit</div>
                    </div>
                    <div style="background:#f9fafb;padding:12px;border-radius:8px;border:1px solid #e5e7eb">
                        <span style="font-size:10px;color:#6b7280;text-transform:uppercase;font-weight:600">Tarif Bonus / Unit</span>
                        <div style="font-size:14px;font-weight:700;color:#111827;margin-top:2px">%s</div>
                    </div>
                    <div style="background:#eff6ff;padding:12px;border-radius:8px;border:1px solid #bfdbfe">
                        <span style="font-size:10px;color:#1e40af;text-transform:uppercase;font-weight:600">Total Pool Bonus</span>
                        <div style="font-size:15px;font-weight:800;color:#1d4ed8;margin-top:2px">%s</div>
                    </div>
                </div>

                <!-- Pembagian ke 2 Mandor + 1 Kepala Operasional -->
                <div style="margin-bottom:24px">
                    <h4 style="font-size:12px;font-weight:700;color:#374151;margin:0 0 10px;text-transform:uppercase;letter-spacing:0.05em">
                        🎯 Pembagian Bonus (2 Mandor Line + 1 Kepala Operasional)
                    </h4>
                    %s
                </div>

                <!-- Rincian Sumber Bonus per Artikel & Order -->
                <div>
                    <h4 style="font-size:12px;font-weight:700;color:#374151;margin:0 0 10px;text-transform:uppercase;letter-spacing:0.05em">
                        📋 Sumber Bonus per Artikel & Order
                    </h4>
                    <div style="overflow-x:auto;border:1px solid #e5e7eb;border-radius:8px">
                        <table style="width:100%%;border-collapse:collapse;font-size:13px">
                            <thead>
                                <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb">
                                    <th style="padding:10px 12px;font-weight:600;color:#4b5563;text-align:left">Klien</th>
                                    <th style="padding:10px 12px;font-weight:600;color:#4b5563;text-align:left">No. Order</th>
                                    <th style="padding:10px 12px;font-weight:600;color:#4b5563;text-align:left">Artikel / Produk</th>
                                    <th style="padding:10px 12px;font-weight:600;color:#4b5563;text-align:right">Lolos QC</th>
                                    <th style="padding:10px 12px;font-weight:600;color:#4b5563;text-align:right">Kontribusi Bonus</th>
                                </tr>
                            </thead>
                            <tbody>%s</tbody>
                        </table>
                    </div>
                </div>
            </div>',
            number_format($totalPassed),
            $rateFmt,
            $totalPoolFmt,
            $distHtml,
            $rowsHtml,
        );
    }
}
