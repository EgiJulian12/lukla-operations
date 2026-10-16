<?php

namespace App\Filament\Resources\PayrollPeriods\RelationManagers;

use App\Models\PayrollEntry;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/**
 * Rincian upah borongan per penjahit pada sebuah periode (FR-015).
 *
 * Sengaja tanpa aksi edit: baris dibuat oleh perhitungan payroll, bukan oleh
 * penyuntingan manual, sehingga angka yang dibayarkan selalu berasal dari
 * output terverifikasi.
 *
 * Aksi "Rincian" menampilkan breakdown per klien, artikel, dan komponen
 * yang dikerjakan penjahit pada periode ini.
 */
class PayrollEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'entries';

    protected static ?string $title = 'Rincian Upah Borongan';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Penjahit')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('articles')
                    ->label('Artikel')
                    ->state(function (PayrollEntry $record): array {
                        $breakdown = $record->getGroupedWorkBreakdown();

                        return collect($breakdown)
                            ->pluck('article_name')
                            ->filter()
                            ->unique()
                            ->values()
                            ->all();
                    })
                    ->badge()
                    ->color('info')
                    ->placeholder('-'),
                TextColumn::make('total_pieces')
                    ->label('Total Pcs')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('sewing_amount')
                    ->label('Ongkos Jahit')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('meal_allowance_amount')
                    ->label('Uang Makan')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('IDR')
                    ->weight('bold')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('rincian')
                    ->label('Rincian')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->color('gray')
                    ->modalHeading(fn (PayrollEntry $record): string => 'Rincian Upah — '.$record->user?->name)
                    ->modalContent(fn (PayrollEntry $record): HtmlString => new HtmlString(self::buildEntryDetailHtml($record)))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
            ])
            ->defaultSort('total_amount', 'desc');
    }

    /**
     * HTML detail breakdown per artikel, order, dan komponen untuk satu PayrollEntry.
     */
    private static function buildEntryDetailHtml(PayrollEntry $record): string
    {
        $breakdown = $record->getGroupedWorkBreakdown();
        $tailorName = htmlspecialchars($record->user?->name ?? '-');
        $sewingFmt = 'Rp '.number_format((float) $record->sewing_amount, 0, ',', '.');
        $mealFmt = 'Rp '.number_format((float) $record->meal_allowance_amount, 0, ',', '.');
        $totalFmt = 'Rp '.number_format((float) $record->total_amount, 0, ',', '.');
        $totalPcs = number_format($record->total_pieces);

        // ── Summary cards ──────────────────────────────────────────────────
        $summaryCards = sprintf(
            '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin-bottom:20px">
                <div style="background:#f9fafb;padding:12px;border-radius:8px;border:1px solid #e5e7eb">
                    <span style="font-size:10px;color:#6b7280;text-transform:uppercase;font-weight:600">Penjahit</span>
                    <div style="font-size:13px;font-weight:700;color:#111827;margin-top:2px">%s</div>
                </div>
                <div style="background:#eff6ff;padding:12px;border-radius:8px;border:1px solid #bfdbfe">
                    <span style="font-size:10px;color:#1e40af;text-transform:uppercase;font-weight:600">Total Pcs</span>
                    <div style="font-size:13px;font-weight:700;color:#1d4ed8;margin-top:2px">%s pcs</div>
                </div>
                <div style="background:#f9fafb;padding:12px;border-radius:8px;border:1px solid #e5e7eb">
                    <span style="font-size:10px;color:#6b7280;text-transform:uppercase;font-weight:600">Ongkos Jahit</span>
                    <div style="font-size:13px;font-weight:700;color:#111827;margin-top:2px">%s</div>
                </div>
                <div style="background:#f9fafb;padding:12px;border-radius:8px;border:1px solid #e5e7eb">
                    <span style="font-size:10px;color:#6b7280;text-transform:uppercase;font-weight:600">Uang Makan</span>
                    <div style="font-size:13px;font-weight:700;color:#111827;margin-top:2px">%s</div>
                </div>
                <div style="background:#f0fdf4;padding:12px;border-radius:8px;border:1px solid #bbf7d0">
                    <span style="font-size:10px;color:#166534;text-transform:uppercase;font-weight:600">Total Diterima</span>
                    <div style="font-size:13px;font-weight:700;color:#15803d;margin-top:2px">%s</div>
                </div>
            </div>',
            $tailorName,
            $totalPcs,
            $sewingFmt,
            $mealFmt,
            $totalFmt,
        );

        // ── Breakdown rows per artikel / order / komponen ──────────────────
        $rowsHtml = '';

        if (empty($breakdown)) {
            $rowsHtml = '<tr><td colspan="4" style="padding:16px;text-align:center;color:#6b7280;font-style:italic">Tidak ada output terverifikasi pada periode ini.</td></tr>';
        } else {
            $prevArticle = null;

            foreach ($breakdown as $row) {
                $client = htmlspecialchars($row['client_name']);
                $order = htmlspecialchars($row['order_number']);
                $article = htmlspecialchars($row['article_name']);
                $component = htmlspecialchars($row['component_name']);
                $pcs = number_format($row['total_pieces']);

                // Artikel header row sebagai pemisah visual
                if ($article !== $prevArticle) {
                    $rowsHtml .= sprintf(
                        '<tr style="background:#f0f9ff;border-bottom:1px solid #bae6fd">
                            <td colspan="4" style="padding:8px 14px;font-size:11px;font-weight:700;color:#0369a1;text-transform:uppercase;letter-spacing:0.05em">
                                📦 %s
                            </td>
                        </tr>',
                        $article,
                    );
                    $prevArticle = $article;
                }

                $rowsHtml .= sprintf(
                    '<tr style="border-bottom:1px solid #f3f4f6">
                        <td style="padding:9px 14px;color:#374151;font-size:13px">%s</td>
                        <td style="padding:9px 14px;color:#6b7280;font-size:13px">%s</td>
                        <td style="padding:9px 14px">
                            <span style="display:inline-block;padding:2px 8px;border-radius:9999px;background:#eff6ff;color:#1d4ed8;font-size:12px;font-weight:500">%s</span>
                        </td>
                        <td style="padding:9px 14px;text-align:right;font-weight:600;color:#111827;font-size:13px">%s pcs</td>
                    </tr>',
                    $client,
                    $order,
                    $component,
                    $pcs,
                );
            }
        }

        return sprintf(
            '<div>%s
                <h4 style="font-size:12px;font-weight:600;color:#374151;margin:0 0 10px;text-transform:uppercase;letter-spacing:0.05em">
                    Rincian per Artikel / Order / Komponen
                </h4>
                <div style="overflow-x:auto;border:1px solid #e5e7eb;border-radius:8px">
                    <table style="width:100%%;border-collapse:collapse;font-size:13px">
                        <thead>
                            <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb">
                                <th style="padding:10px 14px;font-weight:600;color:#4b5563;text-align:left">Klien</th>
                                <th style="padding:10px 14px;font-weight:600;color:#4b5563;text-align:left">No. Order</th>
                                <th style="padding:10px 14px;font-weight:600;color:#4b5563;text-align:left">Komponen</th>
                                <th style="padding:10px 14px;font-weight:600;color:#4b5563;text-align:right">Pcs</th>
                            </tr>
                        </thead>
                        <tbody>%s</tbody>
                    </table>
                </div>
            </div>',
            $summaryCards,
            $rowsHtml,
        );
    }
}
