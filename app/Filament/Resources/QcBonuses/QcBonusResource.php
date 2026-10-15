<?php

namespace App\Filament\Resources\QcBonuses;

use App\Enums\UserRole;
use App\Filament\Resources\QcBonuses\Pages\ListQcBonuses;
use App\Models\PayrollPeriod;
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
 * Tampilan khusus untuk peran QC Inspector guna melihat tarif dan perolehan
 * bonus QC per periode beserta proyek klien dan artikel yang lolos QC.
 */
class QcBonusResource extends Resource
{
    protected static ?string $model = PayrollPeriod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Quality Control';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'Bonus QC Saya';

    protected static ?string $pluralModelLabel = 'Bonus QC Saya';

    protected static ?string $slug = 'qc-bonuses';

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole(UserRole::QC_INSPECTOR) || $user->hasRole(UserRole::OWNER_EXECUTIVE);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['qcBonusPool.distributions']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('period_start')
                    ->label('Awal Periode')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('period_end')
                    ->label('Akhir Periode')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('projects')
                    ->label('Proyek / Klien')
                    ->state(function (PayrollPeriod $record): array {
                        $breakdown = $record->getGroupedQcPassedBreakdown();

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
                    ->placeholder('Belum ada unit lolos'),
                TextColumn::make('articles')
                    ->label('Artikel / Produk')
                    ->state(function (PayrollPeriod $record): array {
                        $breakdown = $record->getGroupedQcPassedBreakdown();

                        if (empty($breakdown)) {
                            return [];
                        }

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
                TextColumn::make('qcBonusPool.total_passed_units')
                    ->label('Unit Lolos QC')
                    ->numeric()
                    ->placeholder('-'),
                TextColumn::make('qc_bonus_rate_per_unit')
                    ->label('Tarif Bonus / Unit')
                    ->money('IDR')
                    ->placeholder('-'),
                TextColumn::make('qcBonusPool.total_pool')
                    ->label('Total Pool Bonus')
                    ->money('IDR')
                    ->placeholder('-'),
                TextColumn::make('my_bonus')
                    ->label('Bonus Diterima')
                    ->state(function (PayrollPeriod $record): ?float {
                        $pool = $record->qcBonusPool;
                        if (! $pool) {
                            return null;
                        }

                        $userDistribution = $pool->distributions
                            ->firstWhere('user_id', auth()->id());

                        if ($userDistribution) {
                            return (float) $userDistribution->amount;
                        }

                        return $pool->amount_per_recipient ? (float) $pool->amount_per_recipient : null;
                    })
                    ->money('IDR')
                    ->weight('bold')
                    ->color('success')
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->actions([
                Action::make('view_details')
                    ->label('Rincian Proyek')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->color('primary')
                    ->modalHeading('Rincian Proyek Lolos QC Pada Periode Ini')
                    ->modalContent(fn (PayrollPeriod $record): HtmlString => new HtmlString(self::buildDetailHtml($record)))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
            ])
            ->defaultSort('period_start', 'desc');
    }

    private static function buildDetailHtml(PayrollPeriod $record): string
    {
        $breakdown = $record->getGroupedQcPassedBreakdown();
        $periodStart = $record->period_start?->format('d/m/Y') ?? '-';
        $periodEnd = $record->period_end?->format('d/m/Y') ?? '-';
        $totalPassed = number_format((int) ($record->qcBonusPool?->total_passed_units ?? 0));
        $ratePerUnit = 'Rp '.number_format((float) ($record->qc_bonus_rate_per_unit ?? 0), 0, ',', '.');
        $totalPool = 'Rp '.number_format((float) ($record->qcBonusPool?->total_pool ?? 0), 0, ',', '.');

        $pool = $record->qcBonusPool;
        $userDist = $pool?->distributions?->firstWhere('user_id', auth()->id());
        $myBonusAmount = $userDist
            ? (float) $userDist->amount
            : (float) ($pool?->amount_per_recipient ?? 0);
        $myBonusFormatted = 'Rp '.number_format($myBonusAmount, 0, ',', '.');

        $rowsHtml = '';
        if (empty($breakdown)) {
            $rowsHtml = '<tr><td colspan="4" style="padding:16px;text-align:center;color:#6b7280;font-style:italic">Tidak ada unit lolos QC yang tercatat pada periode ini.</td></tr>';
        } else {
            foreach ($breakdown as $row) {
                $client = htmlspecialchars($row['client_name']);
                $order = htmlspecialchars($row['order_number']);
                $article = htmlspecialchars($row['article_name']);
                $passed = number_format($row['total_passed']);

                $rowsHtml .= "<tr style=\"border-bottom:1px solid #e5e7eb\">
                    <td style=\"padding:10px 14px;color:#111827;font-weight:600\">{$client}</td>
                    <td style=\"padding:10px 14px;color:#4b5563\">{$order}</td>
                    <td style=\"padding:10px 14px;color:#4b5563\">{$article}</td>
                    <td style=\"padding:10px 14px;text-align:right;font-weight:600;color:#16a34a\">{$passed} unit</td>
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
                <div style=\"background:#f0fdf4;padding:12px;border-radius:8px;border:1px solid #bbf7d0\">
                    <span style=\"font-size:11px;color:#166534;text-transform:uppercase;font-weight:600\">Total Unit Lolos QC</span>
                    <div style=\"font-size:14px;font-weight:700;color:#15803d;margin-top:2px\">{$totalPassed} unit</div>
                </div>
                <div style=\"background:#f9fafb;padding:12px;border-radius:8px;border:1px solid #e5e7eb\">
                    <span style=\"font-size:11px;color:#6b7280;text-transform:uppercase;font-weight:600\">Tarif Bonus / Unit</span>
                    <div style=\"font-size:14px;font-weight:600;color:#111827;margin-top:2px\">{$ratePerUnit}</div>
                </div>
                <div style=\"background:#eff6ff;padding:12px;border-radius:8px;border:1px solid #bfdbfe\">
                    <span style=\"font-size:11px;color:#1e40af;text-transform:uppercase;font-weight:600\">Bonus Anda</span>
                    <div style=\"font-size:14px;font-weight:700;color:#1d4ed8;margin-top:2px\">{$myBonusFormatted}</div>
                </div>
            </div>

            <h4 style=\"font-size:13px;font-weight:600;color:#374151;margin:0 0 10px;text-transform:uppercase;letter-spacing:0.05em\">
                Daftar Proyek & Unit Lolos QC
            </h4>
            <div style=\"overflow-x:auto;border:1px solid #e5e7eb;border-radius:8px\">
                <table style=\"width:100%;border-collapse:collapse;font-size:13px;text-align:left\">
                    <thead>
                        <tr style=\"background:#f9fafb;border-bottom:1px solid #e5e7eb\">
                            <th style=\"padding:10px 14px;font-weight:600;color:#4b5563\">Klien</th>
                            <th style=\"padding:10px 14px;font-weight:600;color:#4b5563\">No. Order / Proyek</th>
                            <th style=\"padding:10px 14px;font-weight:600;color:#4b5563\">Artikel / Produk</th>
                            <th style=\"padding:10px 14px;font-weight:600;color:#4b5563;text-align:right\">Unit Lolos</th>
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
            'index' => ListQcBonuses::route('/'),
        ];
    }
}
