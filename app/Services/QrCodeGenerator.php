<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Renders inline SVG QR codes, so receipts print without any external request.
 */
class QrCodeGenerator
{
    public function svg(string $text, int $size = 180): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd));

        // Drop the XML declaration so the markup can be embedded in a page.
        return preg_replace('/<\?xml.*?\?>\s*/', '', $writer->writeString($text)) ?? '';
    }

    /**
     * A data URI, for places where inline SVG is awkward (e.g. an <img> tag).
     */
    public function dataUri(string $text, int $size = 180): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->svg($text, $size));
    }
}
