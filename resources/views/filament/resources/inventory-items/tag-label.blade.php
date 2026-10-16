<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Label Bahan - {{ $record->tag_id }}</title>
    <style>
        @page {
            size: 100mm 75mm;
            margin: 0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 8px;
            color: #111827;
            background: #ffffff;
            font-size: 11px;
            box-sizing: border-box;
        }
        .label-container {
            border: 2px solid #111827;
            border-radius: 6px;
            padding: 8px;
            height: calc(100% - 20px);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #111827;
            padding-bottom: 4px;
        }
        .company-name {
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .badge {
            background: #111827;
            color: #ffffff;
            font-size: 9px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 3px;
            text-transform: uppercase;
        }
        .barcode-section {
            text-align: center;
            margin: 6px 0;
            padding: 4px;
            background: #f9fafb;
            border: 1px dashed #d1d5db;
            border-radius: 4px;
        }
        .barcode-graphic {
            font-family: "Courier New", Courier, monospace;
            font-size: 24px;
            letter-spacing: 4px;
            font-weight: bold;
            line-height: 1;
        }
        .tag-code {
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 1px;
            margin-top: 3px;
            color: #111827;
        }
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 8px;
            font-size: 10px;
            margin: 4px 0;
        }
        .field {
            display: flex;
            flex-direction: column;
        }
        .field-label {
            font-size: 8px;
            text-transform: uppercase;
            color: #6b7280;
            font-weight: 600;
        }
        .field-value {
            font-size: 11px;
            font-weight: 700;
            color: #111827;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .footer {
            border-top: 1px solid #e5e7eb;
            padding-top: 4px;
            font-size: 8px;
            color: #6b7280;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        @media print {
            .no-print {
                display: none !important;
            }
        }
        .print-btn {
            position: fixed;
            top: 10px;
            right: 10px;
            background: #f59e0b;
            color: #ffffff;
            border: none;
            padding: 8px 14px;
            font-size: 12px;
            font-weight: bold;
            border-radius: 4px;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <button onclick="window.print()" class="print-btn no-print">🖨️ Cetak Label</button>

    <div class="label-container">
        <div class="header">
            <span class="company-name">LUKLA OPERATIONS</span>
            <span class="badge">{{ $record->state->getLabel() }}</span>
        </div>

        <div class="barcode-section">
            <div class="barcode-graphic">||| | |||| | ||| |||| | ||</div>
            <div class="tag-code">{{ $record->tag_id }}</div>
        </div>

        <div class="details-grid">
            <div class="field">
                <span class="field-label">Klien</span>
                <span class="field-value">{{ $record->client->name ?? '-' }}</span>
            </div>
            <div class="field">
                <span class="field-label">No. Pesanan</span>
                <span class="field-value">{{ $record->order->order_number ?? '-' }}</span>
            </div>
            <div class="field">
                <span class="field-label">Bahan / Aksesoris</span>
                <span class="field-value">{{ $record->material->name ?? '-' }}</span>
            </div>
            <div class="field">
                <span class="field-label">Kuantitas</span>
                <span class="field-value">{{ number_format($record->quantity, 2) }} {{ $record->uom }}</span>
            </div>
            <div class="field">
                <span class="field-label">Batch / Lot</span>
                <span class="field-value">{{ $record->batch ?: '-' }}</span>
            </div>
            <div class="field">
                <span class="field-label">Lokasi Rak</span>
                <span class="field-value">{{ $record->location ?: '-' }}</span>
            </div>
        </div>

        <div class="footer">
            <span>Diterima: {{ $record->created_at?->format('d/m/Y H:i') }}</span>
            <span>Tempelkan pada fisik kemasan/roll</span>
        </div>
    </div>
</body>
</html>
