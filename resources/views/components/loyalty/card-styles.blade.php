{{-- Shared by the on-screen preview and the print sheets. Everything is sized
     in millimetres to the ID-1 format (CR80, 85.6 × 53.98 mm) — the size of a
     bank card — so a print at 100% comes out card-sized. --}}
@once
    <style>
        .lc {
            position: relative;
            box-sizing: border-box;
            flex: none;
            width: 85.6mm;
            height: 53.98mm;
            overflow: hidden;
            border-radius: 3.18mm;
            background: var(--lc-bg, #fff);
            color: var(--lc-fg, #0f172a);
            font-family: 'Instrument Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            line-height: 1.2;
            text-align: left;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08), 0 10px 24px -10px rgba(15, 23, 42, 0.35);
            /* Browsers drop backgrounds when printing unless told otherwise. */
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .lc *, .lc *::before, .lc *::after { box-sizing: border-box; }

        /* Front */
        .lc-front {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 4.5mm 5mm 4.2mm;
        }

        .lc-front::before,
        .lc-front::after,
        .lc-ring {
            content: '';
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }

        .lc-front::before {
            width: 70mm;
            height: 70mm;
            top: -34mm;
            right: -28mm;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.24) 0%, rgba(255, 255, 255, 0) 68%);
        }

        .lc-front::after {
            width: 46mm;
            height: 46mm;
            right: -12mm;
            bottom: -27mm;
            border: 0.5mm solid rgba(255, 255, 255, 0.18);
        }

        .lc-ring {
            width: 30mm;
            height: 30mm;
            right: 9mm;
            bottom: -19mm;
            border: 0.35mm solid rgba(255, 255, 255, 0.12);
        }

        .lc-top,
        .lc-bottom {
            position: relative;
            display: flex;
            justify-content: space-between;
            gap: 3mm;
        }

        .lc-top { align-items: flex-start; }
        .lc-bottom { align-items: flex-end; }

        .lc-brand {
            display: flex;
            align-items: center;
            gap: 2.2mm;
            min-width: 0;
        }

        .lc-logo {
            display: flex;
            flex: none;
            align-items: center;
            justify-content: center;
            width: 8.5mm;
            height: 8.5mm;
            border-radius: 2mm;
            background: var(--lc-fg);
            color: var(--lc-logo);
            font-size: 2.6mm;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .lc-store {
            display: block;
            font-size: 3mm;
            font-weight: 700;
            white-space: nowrap;
        }

        .lc-program {
            display: block;
            margin-top: 0.7mm;
            color: var(--lc-muted);
            font-size: 2.1mm;
            letter-spacing: 0.02em;
            white-space: nowrap;
        }

        .lc-tier {
            flex: none;
            padding: 0.9mm 2.3mm;
            border: 0.3mm solid var(--lc-accent);
            border-radius: 99mm;
            color: var(--lc-accent);
            font-size: 1.9mm;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .lc-number {
            position: relative;
            font-family: ui-monospace, 'SF Mono', Menlo, Consolas, monospace;
            font-size: 4.4mm;
            letter-spacing: 0.08em;
            word-spacing: 1.4mm;
            text-shadow: 0 0.2mm 0.3mm rgba(0, 0, 0, 0.2);
        }

        .lc-label {
            display: block;
            color: var(--lc-muted);
            font-size: 1.7mm;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .lc-name {
            display: block;
            max-width: 56mm;
            margin-top: 0.6mm;
            overflow: hidden;
            font-size: 3.1mm;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-overflow: ellipsis;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .lc-since { text-align: right; }

        .lc-value {
            display: block;
            margin-top: 0.6mm;
            font-family: ui-monospace, 'SF Mono', Menlo, Consolas, monospace;
            font-size: 2.8mm;
            font-weight: 600;
        }

        /* Back */
        .lc-back {
            background: #ffffff;
            color: #0f172a;
            outline: 0.2mm solid #e2e8f0;
            outline-offset: -0.2mm;
        }

        .lc-band {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 8mm;
            padding: 0 5mm;
            background: var(--lc-band);
            color: #ffffff;
            font-size: 2.3mm;
            font-weight: 600;
            letter-spacing: 0.03em;
        }

        .lc-band span + span { font-weight: 500; opacity: 0.85; }

        .lc-back-body {
            display: flex;
            align-items: center;
            gap: 3.5mm;
            padding: 2.8mm 5mm 0;
        }

        .lc-barcode { flex: 1; min-width: 0; }
        .lc-barcode svg { display: block; width: 100%; height: auto; }

        .lc-qr {
            flex: none;
            width: 16mm;
            text-align: center;
        }

        .lc-qr svg { display: block; width: 16mm; height: 16mm; }

        .lc-qr span {
            display: block;
            margin-top: 0.5mm;
            color: #475569;
            font-size: 1.55mm;
            line-height: 1.2;
        }

        .lc-terms {
            margin: 2mm 5mm 0;
            color: #475569;
            font-size: 1.7mm;
            line-height: 1.35;
        }

        .lc-contact {
            position: absolute;
            right: 5mm;
            bottom: 2.4mm;
            left: 5mm;
            display: flex;
            justify-content: space-between;
            gap: 2mm;
            color: #0f172a;
            font-size: 1.8mm;
            font-weight: 600;
        }
    </style>
@endonce
