@php($symbol = config('inventory.currency_symbol'))
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Barcode labels · {{ config('app.name') }}</title>
    <style>
        /* Deliberately standalone: a label sheet must print the same whatever
           the app's stylesheet happens to be doing. */
        @page { size: A4 portrait; margin: 10mm; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #f1f5f9;
            color: #0f172a;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
        }

        .sheet {
            width: 190mm;
            margin: 0 auto;
            padding: 10mm 0;
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 8mm;
        }

        .toolbar h1 { margin: 0; font-size: 16px; }
        .toolbar p { margin: 2px 0 0; font-size: 12px; color: #64748b; }

        .toolbar button {
            border: 1px solid #0f172a;
            border-radius: 6px;
            background: #0f172a;
            color: #fff;
            padding: 8px 16px;
            font-size: 13px;
            cursor: pointer;
        }

        .grid {
            display: grid;
            /* Two per row keeps each bar around 0.7mm — comfortably above the
               EAN-13 nominal width, so the labels read off plain paper rather
               than needing a perfect print. */
            grid-template-columns: repeat(2, 1fr);
            gap: 5mm;
        }

        .label {
            /* Kept inside one page so a barcode is never split across a break. */
            break-inside: avoid;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #fff;
            padding: 4mm;
            text-align: center;
        }

        .label .name {
            font-size: 12px;
            font-weight: 600;
            line-height: 1.25;
            height: 2.5em;
            overflow: hidden;
        }

        .label .meta {
            margin-top: 1mm;
            font-size: 10px;
            color: #64748b;
        }

        .label .price {
            margin-top: 1mm;
            font-size: 15px;
            font-weight: 700;
        }

        .label svg { display: block; width: 100%; height: auto; margin-top: 1.5mm; }

        .note {
            margin-top: 6mm;
            font-size: 11px;
            color: #64748b;
        }

        @media print {
            body { background: #fff; }
            .toolbar, .note { display: none; }
            .sheet { width: auto; padding: 0; }
            .label { border-color: #94a3b8; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="toolbar">
            <div>
                <h1>Barcode labels</h1>
                <p>{{ $products->count() }} product(s) · A4 portrait, 2 per row</p>
            </div>
            <button type="button" onclick="window.print()">Print / Save as PDF</button>
        </div>

        <div class="grid">
            @foreach ($products as $product)
                <div class="label">
                    <div class="name">{{ $product->display_name }}</div>
                    <div class="meta">{{ $product->sku }} · {{ $product->category?->name }}</div>
                    <div class="price">{{ $symbol }}{{ number_format((float) $product->selling_price, 2) }}</div>
                    {!! $barcodes->ean13($product->barcode, 34) !!}
                </div>
            @endforeach
        </div>

        @if ($skipped > 0)
            <p class="note">
                {{ $skipped }} product(s) were left off: their barcode is not a valid EAN-13, so a scanner
                would refuse to read the label.
            </p>
        @endif
    </div>
</body>
</html>
