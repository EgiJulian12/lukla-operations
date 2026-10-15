<?php

namespace App\Filament\Resources\TailorWages;

use App\Enums\UserRole;
use App\Filament\Resources\TailorWages\Pages\ListTailorWages;
use App\Models\PayrollEntry;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Tampilan khusus untuk Penjahit guna melihat upah borongan dan uang makan
 * miliknya sendiri beserta proyek klien dan komponen yang dikerjakan.
 */
class TailorWageResource extends Resource
{
    protected static ?string $model = PayrollEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Pekerjaan Saya';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Upah Jahit Saya';

    protected static ?string $pluralModelLabel = 'Upah Jahit Saya';

    protected static ?string $slug = 'tailor-wages';

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole(UserRole::TAILOR_OPERATOR) || $user->hasRole(UserRole::OWNER_EXECUTIVE);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['payrollPeriod'])
            ->where('user_id', auth()->id());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payrollPeriod.period_start')
                    ->label('Awal Periode')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('payrollPeriod.period_end')
                    ->label('Akhir Periode')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('projects')
                    ->label('Proyek / Klien')
                    ->state(function (PayrollEntry $record): array {
                        $breakdown = $record->getGroupedWorkBreakdown();

                        if (empty($breakdown)) {
                            return [];
                        }

                        return collect($breakdown)
                            ->map(fn (array $item): string => sprintf('%s (%s)', $item['client_name'], $item['order_number']))
                            ->unique()
                            ->values()
                            ->all();
                    })
                    ->badge()
                    ->color('primary')
                    ->placeholder('Belum ada data output'),
                TextColumn::make('components')
                    ->label('Komponen')
                    ->state(function (PayrollEntry $record): array {
                        $breakdown = $record->getGroupedWorkBreakdown();

                        if (empty($breakdown)) {
                            return [];
                        }

                        return collect($breakdown)
                            ->pluck('component_name')
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
                    ->label('Total Diterima')
                    ->money('IDR')
                    ->weight('bold')
                    ->color('success')
                    ->sortable(),
                TextColumn::make('payrollPeriod.status')
                    ->label('Status Pembayaran')
                    ->badge(),
            ])
            ->actions([
                Action::make('view_details')
                    ->label('Rincian Proyek')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->color('primary')
                    ->modalHeading('Rincian Proyek & Komponen Dikerjakan')
                    ->modalContent(fn (PayrollEntry $record): HtmlString => new HtmlString(self::buildDetailHtml($record)))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
            ])
            ->defaultSort('id', 'desc');
    }

    private static function buildDetailHtml(PayrollEntry $record): string
    {
        $breakdown = $record->getGroupedWorkBreakdown();
        $periodStart = $record->payrollPeriod?->period_start?->format('d/m/Y') ?? '-';
        $periodEnd = $record->payrollPeriod?->period_end?->format('d/m/Y') ?? '-';
        $totalPieces = number_format($record->total_pieces);
        $sewingAmount = 'Rp '.number_format((float) $record->sewing_amount, 0, ',', '.');
        $mealAmount = 'Rp '.number_format((float) $record->meal_allowance_amount, 0, ',', '.');
        $totalAmount = 'Rp '.number_format((float) $record->total_amount, 0, ',', '.');

        $rowsHtml = '';
        if (empty($breakdown)) {
            $rowsHtml = '<tr><td colspan="5" style="padding:16px;text-align:center;color:#6b7280;font-style:italic">Tidak ada catatan output terverifikasi pada periode ini.</td></tr>';
        } else {
            foreach ($breakdown as $row) {
                $client = htmlspecialchars($row['client_name']);
                $order = htmlspecialchars($row['order_number']);
                $article = htmlspecialchars($row['article_name']);
                $comp = htmlspecialchars($row['component_name']);
                $pcs = number_format($row['total_pieces']);

                $rowsHtml .= "<tr style=\"border-bottom:1px solid #e5e7eb\">
                    <td style=\"padding:10px 14px;color:#111827;font-weight:600\">{$client}</td>
                    <td style=\"padding:10px 14px;color:#4b5563\">{$order}</td>
                    <td style=\"padding:10px 14px;color:#4b5563\">{$article}</td>
                    <td style=\"padding:10px 14px\"><span style=\"display:inline-block;padding:2px 8px;border-radius:9999px;background:#eff6ff;color:#1d4ed8;font-size:12px;font-weight:500\">{$comp}</span></td>
                    <td style=\"padding:10px 14px;text-align:right;font-weight:600;color:#111827\">{$pcs} pcs</td>
                </tr>";
            }
        }

        return "<div>
            <div style=\"display:grid;grid-template-columns:repeat(auto-fit, minmax(130px, 1fr));gap:12px;margin-bottom:20px\">
                <div style=\"background:#f9fafb;padding:12px;border-radius:8px;border:1px solid #e5e7eb\">
                    <span style=\"font-size:11px;color:#6b7280;text-transform:uppercase;font-weight:600\">Awal Periode</span>
                    <div style=\"font-size:14px;font-weight:600;color:#111827;margin-top:2px\">{$periodStart}</div>
                </div>
                <div style=\"background:#f9fafb;padding:12px;border-radius:8px;border:1px solid #e5e7eb\">
                    <span style=\"font-size:11px;color:#6b7280;text-transform:uppercase;font-weight:600\">Akhir Periode</span>
                    <div style=\"font-size:14px;font-weight:600;color:#111827;margin-top:2px\">{$periodEnd}</div>
                </div>
                <div style=\"background:#eff6ff;padding:12px;border-radius:8px;border:1px solid #bfdbfe\">
                    <span style=\"font-size:11px;color:#1e40af;text-transform:uppercase;font-weight:600\">Output Anda</span>
                    <div style=\"font-size:14px;font-weight:700;color:#1d4ed8;margin-top:2px\">{$totalPieces} pcs</div>
                </div>
                <div style=\"background:#f0fdf4;padding:12px;border-radius:8px;border:1px solid #bbf7d0\">
                    <span style=\"font-size:11px;color:#166534;text-transform:uppercase;font-weight:600\">Total Upah (Jahit + Makan)</span>
                    <div style=\"font-size:14px;font-weight:700;color:#15803d;margin-top:2px\">{$totalAmount}</div>
                </div>
            </div>

            <h4 style=\"font-size:13px;font-weight:600;color:#374151;margin:0 0 10px;text-transform:uppercase;letter-spacing:0.05em\">
                Daftar Proyek & Komponen Dikerjakan
            </h4>
            <div style=\"overflow-x:auto;border:1px solid #e5e7eb;border-radius:8px\">
                <table style=\"width:100%;border-collapse:collapse;font-size:13px;text-align:left\">
                    <thead>
                        <tr style=\"background:#f9fafb;border-bottom:1px solid #e5e7eb\">
                            <th style=\"padding:10px 14px;font-weight:600;color:#4b5563\">Klien</th>
                            <th style=\"padding:10px 14px;font-weight:600;color:#4b5563\">No. Order / Proyek</th>
                            <th style=\"padding:10px 14px;font-weight:600;color:#4b5563\">Artikel</th>
                            <th style=\"padding:10px 14px;font-weight:600;color:#4b5563\">Komponen</th>
                            <th style=\"padding:10px 14px;font-weight:600;color:#4b5563;text-align:right\">Output (Pcs)</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$rowsHtml}
                    </tbody>
                </table>
            </div>
        </div>";
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTailorWages::route('/'),
        ];
    }
}
