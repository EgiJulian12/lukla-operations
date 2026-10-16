<?php

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Str;

/**
 * Renderer dokumen penawaran harga (Proforma Invoice) untuk Order.
 * Menghasilkan output visual yang sama persis dengan layout Invoice dokumen akhir,
 * sehingga penawaran awal antara owner dan klien selaras dengan format invoice.
 */
class QuoteDocumentRenderer
{
    public static function renderHtml(Order $order): string
    {
        [$statusBg, $statusText, $statusDot] = match ($order->status) {
            OrderStatus::REQUESTED => ['#f3f4f6', '#374151', '#9ca3af'],
            OrderStatus::QUOTED => ['#fffbeb', '#92400e', '#f59e0b'],
            OrderStatus::QUOTE_APPROVED => ['#eff6ff', '#1e40af', '#3b82f6'],
            OrderStatus::CANCELLED => ['#fef2f2', '#991b1b', '#ef4444'],
            default => ['#f0fdf4', '#14532d', '#22c55e'],
        };

        $statusLabel = match ($order->status) {
            OrderStatus::QUOTED => 'Penawaran Diberikan (Menunggu ACC)',
            OrderStatus::QUOTE_APPROVED => 'Penawaran Disetujui Klien',
            default => $order->status->getLabel(),
        };

        $clientName = htmlspecialchars($order->client?->name ?? '-');
        $orderNumber = htmlspecialchars($order->order_number);
        $docNumber = 'SPH-'.$orderNumber;
        $workSchemeLabel = htmlspecialchars($order->work_scheme?->getLabel() ?? '-');
        $qty = (int) ($order->quantity ?? 0);
        $estimatedCost = (float) ($order->estimated_cost ?? 0);
        $unitPrice = $qty > 0 ? round($estimatedCost / $qty, 2) : 0.0;

        $amountFmt = 'Rp '.number_format($estimatedCost, 0, ',', '.');
        $unitPriceFmt = 'Rp '.number_format($unitPrice, 0, ',', '.');
        $startDateFmt = $order->start_date ? $order->start_date->format('d M Y') : '-';
        $dueDateFmt = $order->due_date ? $order->due_date->format('d M Y') : '-';
        $quotedAtFmt = $order->quoted_at ? $order->quoted_at->format('d M Y, H:i') : '-';
        $quotedByName = htmlspecialchars($order->quotedBy?->name ?? 'Owner / Pabrik');

        $articleName = htmlspecialchars($order->article?->name ?? ($order->description ? Str::limit($order->description, 60) : "Produksi Pesanan #{$orderNumber}"));

        // ── 1. Header (Hero) ────────────────────────────────────────────────
        $hero = sprintf(
            '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
                <div>
                    <p style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#6b7280;margin:0 0 4px">Surat Penawaran Harga (Proforma Invoice)</p>
                    <p style="font-size:20px;font-weight:700;color:#111827;margin:0">%s</p>
                </div>
                <div style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:9999px;background:%s">
                    <span style="width:8px;height:8px;border-radius:50%%;background:%s;flex-shrink:0"></span>
                    <span style="font-size:13px;font-weight:600;color:%s">%s</span>
                </div>
            </div>',
            $docNumber,
            $statusBg,
            $statusDot,
            $statusText,
            $statusLabel,
        );

        // ── 2. Kartu Info Utama (2 kolom) ───────────────────────────────────
        $notesRow = $order->quote_notes
            ? sprintf(
                '<div style="grid-column:1/-1;padding-top:12px;border-top:1px solid #e5e7eb">
                    <p style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#9ca3af;margin:0 0 4px">Catatan Penawaran Owner</p>
                    <p style="font-size:13px;color:#374151;margin:0">%s</p>
                </div>',
                htmlspecialchars($order->quote_notes),
            )
            : '';

        $infoCard = sprintf(
            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:16px;margin-bottom:20px">
                %s%s%s%s%s%s%s%s%s
            </div>',
            self::infoCell('Klien', $clientName),
            self::infoCell('No. Pesanan', "<strong>{$orderNumber}</strong>"),
            self::infoCell('Skema Maklon', $workSchemeLabel),
            self::infoCell('Total Estimasi Biaya', '<span style="font-size:16px;font-weight:700;color:#1d4ed8">'.$amountFmt.'</span>'),
            self::infoCell('Penawar', $quotedByName),
            self::infoCell('Tanggal Penawaran', $quotedAtFmt),
            self::infoCell('Target Mulai', $startDateFmt),
            self::infoCell('Target Selesai', $dueDateFmt),
            $notesRow,
        );

        // ── 3. Tabel Rincian Penawaran (Mirip Format Invoice) ───────────────
        $rows = sprintf(
            '<tr style="background:#ffffff">
                <td style="padding:12px 14px;font-size:13px;color:#111827;font-weight:600;border-bottom:1px solid #f3f4f6">
                    📦 %s
                    %s
                </td>
                <td style="padding:12px 14px;font-size:13px;text-align:right;color:#374151;border-bottom:1px solid #f3f4f6">%s pcs</td>
                <td style="padding:12px 14px;font-size:13px;text-align:right;color:#6b7280;border-bottom:1px solid #f3f4f6">%s</td>
                <td style="padding:12px 14px;font-size:13px;text-align:right;font-weight:600;color:#111827;border-bottom:1px solid #f3f4f6">%s</td>
            </tr>',
            $articleName,
            $order->description && $order->article ? '<div style="font-size:11px;color:#6b7280;font-weight:400;margin-top:2px">'.htmlspecialchars($order->description).'</div>' : '',
            number_format($qty),
            $unitPriceFmt,
            $amountFmt,
        );

        // Subtotal row
        $rows .= sprintf(
            '<tr style="background:#eff6ff;border-top:2px solid #bfdbfe">
                <td colspan="3" style="padding:11px 14px;font-size:13px;font-weight:600;color:#1e40af">Subtotal Penawaran</td>
                <td style="padding:11px 14px;font-size:14px;font-weight:700;text-align:right;color:#1e40af">%s</td>
            </tr>',
            $amountFmt,
        );

        // Total akhir penawaran
        $rows .= sprintf(
            '<tr style="background:#1d4ed8">
                <td colspan="3" style="padding:13px 14px;font-size:14px;font-weight:700;color:#ffffff">Total Penawaran (Estimasi Biaya)</td>
                <td style="padding:13px 14px;font-size:15px;font-weight:800;text-align:right;color:#ffffff">%s</td>
            </tr>',
            $amountFmt,
        );

        $tableSection = sprintf(
            '<div style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;margin-bottom:16px">
                <div style="padding:12px 16px;background:#f1f5f9;border-bottom:1px solid #e5e7eb">
                    <p style="margin:0;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#475569">
                        📋 Rincian Estimasi Item Pesanan — %s
                    </p>
                </div>
                <div style="overflow-x:auto">
                    <table style="width:100%%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#f8fafc">
                                <th style="padding:10px 14px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;text-align:left;border-bottom:2px solid #e5e7eb">Item / Artikel</th>
                                <th style="padding:10px 14px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;text-align:right;border-bottom:2px solid #e5e7eb">Jumlah</th>
                                <th style="padding:10px 14px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;text-align:right;border-bottom:2px solid #e5e7eb">Estimasi Biaya / Pcs</th>
                                <th style="padding:10px 14px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;text-align:right;border-bottom:2px solid #e5e7eb">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>%s</tbody>
                    </table>
                </div>
            </div>',
            $orderNumber,
            $rows,
        );

        // ── 4. Footnote & Syarat Penawaran ───────────────────────────────────
        $footnote = '
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;font-size:12px;color:#64748b;line-height:1.5">
                <p style="font-weight:600;color:#334155;margin:0 0 4px">ℹ️ Informasi Dokumen Penawaran:</p>
                <ul style="margin:0;padding-left:18px">
                    <li>Dokumen ini merupakan penawaran estimasi biaya awal (Proforma) yang disepakati oleh pemilik pabrik dan klien.</li>
                    <li>Invoice resmi akhir akan diterbitkan oleh bagian Finance setelah produksi selesai, mengikuti rincian komponen fisik dan verifikasi output kerja.</li>
                    <li>Biaya overhead pabrik dikelola sebagai catatan internal dan tidak dibebankan sebagai biaya tambahan di luar kesepakatan penawaran.</li>
                </ul>
            </div>';

        return $hero.$infoCard.$tableSection.$footnote;
    }

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
}
