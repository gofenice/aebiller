<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\LoyaltySetting;
use App\Services\BarcodeGenerator;
use App\Services\LoyaltyService;
use Illuminate\View\View;

class PublicMemberController extends Controller
{
    /**
     * The member's own balance page, reached from the QR on the back of the
     * card. It doubles as a digital card: the barcode scans off a phone screen.
     *
     * No authentication, as with the online bill: the uuid is the only key.
     */
    public function __invoke(Customer $customer, LoyaltyService $loyalty, BarcodeGenerator $barcodes): View
    {
        $customer->load(['tier', 'activeCard']);

        return view('members.show', [
            'customer' => $customer,
            'settings' => LoyaltySetting::current(),
            'summary' => $loyalty->memberSummary($customer),
            'transactions' => $customer->transactions()->latest('id')->limit(10)->get(),
            'barcodeSvg' => $customer->is_active && $customer->activeCard !== null
                ? $barcodes->ean13($customer->activeCard->number, 40)
                : null,
        ]);
    }
}
