<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    <x-loyalty.card-styles />
    <style>
        /* Deliberately standalone, like the shelf labels: a card must print the
           same whatever the app's stylesheet happens to be doing. */
        @if ($layout === 'pvc')
            @page { size: 85.6mm 53.98mm; margin: 0; }
        @else
            @page { size: A4 portrait; margin: 10mm; }
        @endif

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #e2e8f0;
            color: #0f172a;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
        }

        .sheet {
            width: 190mm;
            max-width: 100%;
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
        .actions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }

        .switch {
            display: inline-flex;
            overflow: hidden;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #fff;
        }

        .switch a {
            padding: 7px 12px;
            color: #475569;
            font-size: 12px;
            text-decoration: none;
        }

        .switch a.on { background: #0f172a; color: #fff; }

        .toolbar button {
            border: 1px solid #0f172a;
            border-radius: 6px;
            background: #0f172a;
            color: #fff;
            padding: 8px 16px;
            font-size: 13px;
            cursor: pointer;
        }

        .pairs { display: flex; flex-direction: column; gap: 7mm; }

        .group { break-inside: avoid; }

        .pair {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 8mm;
        }

        /* Cut guides, just outside the card edge. */
        .pair .lc { outline: 0.2mm dashed #94a3b8; outline-offset: 1.5mm; }

        .caption {
            margin: 3mm 0 0;
            color: #475569;
            font-size: 11px;
            text-align: center;
        }

        .stack {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8mm;
        }

        .empty {
            padding: 40px;
            border: 1px dashed #94a3b8;
            border-radius: 8px;
            color: #64748b;
            font-size: 13px;
            text-align: center;
        }

        .note {
            margin-top: 8mm;
            color: #64748b;
            font-size: 11px;
            line-height: 1.6;
        }

        @media print {
            body { background: #fff; }
            .toolbar, .note, .caption { display: none; }
            .sheet { width: auto; padding: 0; }
            .lc { box-shadow: none; }
            .stack { gap: 0; }
            /* A card printer takes one face per page: front, then back. */
            .stack .lc { border-radius: 0; outline: 0; break-after: page; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="toolbar">
            <div>
                <h1>{{ $title }}</h1>
                <p>
                    {{ $customers->count() }} card(s) ·
                    {{ $layout === 'pvc' ? 'Card printer — one CR80 face per page' : 'A4 portrait — front and back side by side' }}
                </p>
            </div>
            <div class="actions">
                <nav class="switch">
                    <a href="{{ request()->fullUrlWithQuery(['layout' => 'a4']) }}" @class(['on' => $layout === 'a4'])>A4 sheet</a>
                    <a href="{{ request()->fullUrlWithQuery(['layout' => 'pvc']) }}" @class(['on' => $layout === 'pvc'])>Card printer (CR80)</a>
                </nav>
                <button type="button" onclick="window.print()">Print / Save as PDF</button>
            </div>
        </div>

        @if ($customers->isEmpty())
            <div class="empty">No members with an active card match this selection.</div>
        @elseif ($layout === 'pvc')
            <div class="stack">
                @foreach ($customers as $customer)
                    <x-loyalty.card-front :customer="$customer" :settings="$settings" />
                    <x-loyalty.card-back :customer="$customer" :settings="$settings"
                        :barcode-svg="$barcodes[$customer->id]" :qr-svg="$qrCodes[$customer->id]" />
                @endforeach
            </div>
        @else
            <div class="pairs">
                @foreach ($customers as $customer)
                    <div class="group">
                        <div class="pair">
                            <x-loyalty.card-front :customer="$customer" :settings="$settings" />
                            <x-loyalty.card-back :customer="$customer" :settings="$settings"
                                :barcode-svg="$barcodes[$customer->id]" :qr-svg="$qrCodes[$customer->id]" />
                        </div>
                        @if ($isSample)
                            <p class="caption">
                                <strong>{{ $customer->tier->name }}</strong> — {{ $customer->tier->card_theme->label() }}
                                @if ($customer->tier->perks) · {{ $customer->tier->perks }} @endif
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <p class="note">
            @if ($isSample)
                These are sample cards, one per tier, with a made-up member. Each tier's colour is chosen on the Loyalty Settings page.<br>
            @endif
            <strong>A4 sheet:</strong> print at 100% ("Actual size", not "Fit to page") on 250–300 gsm card, cut along the dashed
            lines, and glue the two faces back to back or laminate them together.<br>
            <strong>Card printer:</strong> choose the CR80 card size with double-sided printing. Each card prints its front, then its back.<br>
            The barcode on the back is what the till scans. Print a test card and scan it before a large run.
        </p>
    </div>
</body>
</html>
