@php
    $symbol = config('inventory.currency_symbol');
    $vatNumber = config('inventory.store_vat_number');
    $address = config('inventory.store_address');
    $phone = config('inventory.store_phone');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $sale->invoice_no }}</title>
    <style>
        /*
         * An 80mm roll prints 72mm wide. The page is given that size with no
         * margins of its own, or the browser scales the whole receipt down to
         * fit and the print comes out small and off-centre.
         */
        @page {
            size: 80mm auto;
            margin: 0;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        body {
            margin: 0 auto;
            padding: 0 2mm;
            width: 72mm;
            font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
            font-size: 12px;
            /* Thermal heads lay down thin strokes faintly: everything is bold. */
            font-weight: 700;
            line-height: 1.25;
            color: #000;
            background: #fff;
        }

        .center { text-align: center; }
        .right { text-align: right; }

        .title {
            font-size: 16px;
            font-weight: 900;
            margin-bottom: 2px;
        }

        .sub {
            font-size: 11px;
            font-weight: 600;
            margin: 1px 0;
        }

        .sep {
            border-top: 1.5px dashed #000;
            margin: 4px 0;
        }

        .sep-solid {
            border-top: 2px solid #000;
            margin: 4px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td, th {
            padding: 2px 0;
            font-size: 11px;
            vertical-align: top;
        }

        th {
            font-weight: 900;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
        }

        .grand-total td {
            font-size: 14px;
            font-weight: 900;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 3px 0;
        }

        .note {
            font-size: 10px;
            font-weight: 600;
        }

        .footer {
            font-size: 11px;
            font-weight: 700;
            margin-top: 4px;
        }

        .voided {
            border: 2px solid #000;
            padding: 3px 0;
            margin: 4px 0;
            font-size: 14px;
            font-weight: 900;
            letter-spacing: 1px;
        }

        .qr {
            margin: 6px auto 2px;
            width: 110px;
        }

        .qr svg {
            width: 110px;
            height: 110px;
            display: block;
        }

        /* The toolbar is for the screen only; it never reaches the roll. */
        .toolbar {
            width: 72mm;
            margin: 8px auto;
            display: flex;
            gap: 8px;
        }

        .toolbar button,
        .toolbar a {
            flex: 1;
            padding: 8px 10px;
            font: inherit;
            font-size: 12px;
            text-align: center;
            text-decoration: none;
            color: #000;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            cursor: pointer;
        }

        @media print {
            .toolbar { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="center">
        <div class="title">{{ config('app.name') }}</div>
        @if ($address)
            <div class="sub">{{ $address }}</div>
        @endif
        @if ($phone)
            <div class="sub">Phone: {{ $phone }}</div>
        @endif
        @if ($vatNumber)
            <div class="sub">VAT No: {{ $vatNumber }}</div>
        @endif
        <div class="sub">Simplified Tax Invoice · فاتورة ضريبية مبسطة</div>
    </div>

    @if ($sale->isVoided())
        <div class="center voided">VOIDED</div>
    @endif

    <div class="sep"></div>

    <table>
        <tr>
            <td>{{ $sale->sold_at->format('d-M-Y H:i') }}</td>
            <td class="right">Bill: {{ $sale->invoice_no }}</td>
        </tr>
        <tr>
            <td>Cashier: {{ $sale->cashier?->name ?? '—' }}</td>
            <td class="right">{{ $sale->payment_method->shortLabel() }}</td>
        </tr>
        @if ($sale->customer_name)
            <tr>
                <td colspan="2">Customer: {{ $sale->customer_name }}</td>
            </tr>
        @endif
        @if ($sale->customer_vat_number)
            <tr>
                <td colspan="2">Customer VAT: {{ $sale->customer_vat_number }}</td>
            </tr>
        @endif
        @if ($sale->customer?->activeCard)
            <tr>
                <td colspan="2">Card: {{ $sale->customer->activeCard->maskedNumber() }}</td>
            </tr>
        @endif
    </table>

    <div class="sep"></div>

    <table>
        <thead>
            <tr>
                <th style="text-align: left; width: 44%;">ITEM</th>
                <th class="center" style="width: 16%;">QTY</th>
                <th class="right" style="width: 20%;">RATE</th>
                <th class="right" style="width: 20%;">AMT</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $item)
                <tr>
                    <td>
                        {{ $item->name }}
                        @if ((float) $item->discount_percent > 0)
                            <div class="note">less {{ rtrim(rtrim((string) $item->discount_percent, '0'), '.') }}%</div>
                        @endif
                    </td>
                    <td class="center">@qty($item->quantity) {{ $item->unit_code }}</td>
                    <td class="right">{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="right">{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="sep"></div>

    <table>
        @if ((float) $sale->line_discount_total > 0)
            <tr>
                <td>Item discounts</td>
                <td class="right">− {{ number_format((float) $sale->line_discount_total, 2) }}</td>
            </tr>
        @endif
        @if ((float) $sale->bill_discount > 0)
            <tr>
                <td>Bill discount</td>
                <td class="right">− {{ number_format((float) $sale->bill_discount, 2) }}</td>
            </tr>
        @endif
        @if ((float) $sale->loyalty_discount > 0)
            <tr>
                <td>Points redeemed ({{ number_format($sale->loyalty_points_redeemed) }})</td>
                <td class="right">− {{ number_format((float) $sale->loyalty_discount, 2) }}</td>
            </tr>
        @endif
        <tr>
            <td>Total excluding VAT</td>
            <td class="right">{{ number_format((float) $sale->subtotal_excl_vat, 2) }}</td>
        </tr>
        <tr>
            <td>VAT</td>
            <td class="right">{{ number_format((float) $sale->vat_total, 2) }}</td>
        </tr>
        <tr class="grand-total">
            <td>NET PAYABLE</td>
            <td class="right">{{ $symbol }}{{ number_format((float) $sale->grand_total, 2) }}</td>
        </tr>
        <tr>
            <td>Paid ({{ $sale->payment_method->shortLabel() }})</td>
            <td class="right">{{ number_format((float) $sale->amount_paid, 2) }}</td>
        </tr>
        @if ((float) $sale->change_due > 0)
            <tr>
                <td>Change</td>
                <td class="right">{{ number_format((float) $sale->change_due, 2) }}</td>
            </tr>
        @endif
        @if ($sale->isCredit() && (float) $sale->amount_outstanding > 0)
            <tr>
                <td>On account</td>
                <td class="right">{{ number_format((float) $sale->amount_outstanding, 2) }}</td>
            </tr>
        @endif
    </table>

    @if ($sale->customer_id !== null && $sale->loyalty_balance_after !== null)
        <div class="sep"></div>
        <table>
            <tr>
                <td>Points earned</td>
                <td class="right">+{{ number_format($sale->loyalty_points_earned) }}</td>
            </tr>
            @if ($sale->loyalty_points_redeemed > 0)
                <tr>
                    <td>Points used</td>
                    <td class="right">−{{ number_format($sale->loyalty_points_redeemed) }}</td>
                </tr>
            @endif
            <tr>
                <td>Points balance</td>
                <td class="right">{{ number_format($sale->loyalty_balance_after) }}</td>
            </tr>
        </table>
    @endif

    <div class="sep-solid"></div>

    <div class="center">
        @if ($qrSvg)
            <div class="qr">{!! $qrSvg !!}</div>
            <div class="note">Scan to open this bill</div>
        @endif
        <div class="footer">
            THANK YOU FOR SHOPPING WITH US<br>
            شكراً لتسوقكم معنا
        </div>
    </div>

    <div class="toolbar">
        <button type="button" onclick="window.print()">Print again</button>
        <a href="{{ route('sales.show', $sale) }}">Back to bill</a>
    </div>

    <script>
        /*
         * Roll printers have no page length of their own: whatever the driver
         * is set to — 210mm, or 3276mm of "continuous" — is fed in full, and
         * the blank remainder arrives at the top of the next receipt.
         *
         * So the page is told how tall this particular bill is, measured once
         * it has been laid out, and the paper ends where the receipt does.
         */
        function fitPageToReceipt() {
            const toolbar = document.querySelector('.toolbar');

            // The toolbar is on the screen but never on the paper, so it must
            // not count towards how long the paper needs to be.
            toolbar.style.display = 'none';

            const pixelsPerMm = 96 / 25.4;
            const height = Math.ceil(document.body.scrollHeight / pixelsPerMm) + 4;

            toolbar.style.display = '';

            const rule = document.createElement('style');
            rule.textContent = '@page { size: 80mm ' + height + 'mm; margin: 0; }';
            document.head.appendChild(rule);
        }

        window.addEventListener('load', () => {
            fitPageToReceipt();

            // Straight to the printer: the till wants one keystroke, not two.
            window.print();
        });
    </script>
</body>
</html>
