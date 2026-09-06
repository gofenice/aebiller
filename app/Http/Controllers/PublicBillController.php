<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\View\View;

class PublicBillController extends Controller
{
    /**
     * The customer-facing bill reached by scanning the receipt QR code.
     *
     * No authentication: the uuid in the URL is the only key, which is what
     * makes the printed QR work for whoever holds the receipt.
     */
    public function __invoke(Sale $sale): View
    {
        $sale->load('items');

        return view('bills.show', ['sale' => $sale]);
    }
}
