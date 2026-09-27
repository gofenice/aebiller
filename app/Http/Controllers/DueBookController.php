<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDueBookEntryRequest;
use App\Models\Customer;
use App\Services\BillingService;
use App\Services\LoyaltyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;

/**
 * Working through the shop's old due book: each line names someone who owed
 * money before the shop billed here, and says how much.
 *
 * A name that is not on the books yet is signed up as it is entered, so the
 * whole book can be typed in from one screen.
 */
class DueBookController extends Controller
{
    public function __construct(
        protected BillingService $billing,
        protected LoyaltyService $loyalty,
    ) {}

    public function create(): View
    {
        $this->authorize('manage-customers');

        return view('customers.due-book', [
            'owing' => Customer::where('opening_due_outstanding', '>', 0)
                ->orderByDesc('opening_due_outstanding')
                ->get(),
            'carriedOver' => (float) Customer::sum('opening_due_outstanding'),
        ]);
    }

    public function store(StoreDueBookEntryRequest $request): RedirectResponse
    {
        $customer = $request->existing();
        $isNew = $customer === null;

        try {
            if ($isNew) {
                $customer = $this->loyalty->enrol(
                    $request->safe()->only(['name', 'phone']),
                    $request->user(),
                );
            }

            $this->billing->setOpeningDue($customer, $request->safe()->only([
                'amount', 'opening_due_on', 'opening_due_note',
            ]));
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        $amount = number_format((float) $request->validated('amount'), 2);

        return redirect()
            ->route('customers.due-book')
            ->with('status', $isNew
                ? "{$customer->name} was signed up owing {$amount}."
                : "{$customer->name} already had an account — their balance is now {$amount}.");
    }
}
