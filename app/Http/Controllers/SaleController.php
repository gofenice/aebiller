<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\User;
use App\Services\BillingService;
use App\Services\QrCodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class SaleController extends Controller
{
    public function __construct(protected BillingService $billing) {}

    /**
     * Bills taken at the till, newest first.
     */
    public function index(Request $request): View
    {
        $this->authorize('run-till');

        $query = Sale::query()
            ->with('cashier')
            ->withCount('items')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = $request->string('search')->toString();
                $query->where(function ($query) use ($term): void {
                    $query->where('invoice_no', 'like', "%{$term}%")
                        ->orWhere('customer_name', 'like', "%{$term}%")
                        ->orWhere('customer_phone', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('payment_method'), fn ($q) => $q->where('payment_method', $request->string('payment_method')->toString()))
            ->when($request->filled('cashier'), fn ($q) => $q->where('cashier_id', $request->integer('cashier')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('sold_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('sold_at', '<=', $request->date('to')));

        $summary = (clone $query)
            ->completed()
            ->select(
                DB::raw('count(*) as bills'),
                DB::raw('coalesce(sum(grand_total), 0) as revenue'),
                DB::raw('coalesce(sum(vat_total), 0) as vat'),
                DB::raw('coalesce(sum(subtotal_excl_vat - cost_total), 0) as profit'),
            )
            ->first();

        return view('sales.index', [
            'sales' => $query->latest('sold_at')->latest('id')->paginate(25)->withQueryString(),
            'summary' => $summary,
            'paymentMethods' => PaymentMethod::cases(),
            'statuses' => SaleStatus::cases(),
            'cashiers' => User::orderBy('name')->get(),
            'todayTotal' => (float) Sale::completed()->whereDate('sold_at', today())->sum('grand_total'),
        ]);
    }

    /**
     * The printable receipt, with the QR that opens the bill online.
     */
    public function show(Sale $sale, QrCodeGenerator $qr): View
    {
        $this->authorize('run-till');

        $sale->load(['items', 'cashier', 'voider']);

        return view('sales.show', [
            'sale' => $sale,
            'publicUrl' => $sale->publicUrl(),
            'qrSvg' => $qr->svg($sale->publicUrl(), 150),
        ]);
    }

    /**
     * Void a bill and put the goods back on the shelf. Super admins only.
     */
    public function destroy(Request $request, Sale $sale): RedirectResponse
    {
        $this->authorize('delete-records');

        $reason = $request->validate([
            'void_reason' => ['nullable', 'string', 'max:255'],
        ])['void_reason'] ?? null;

        try {
            $this->billing->voidSale($sale, $request->user(), $reason);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', "Bill {$sale->invoice_no} was voided and the stock returned.");
    }
}
