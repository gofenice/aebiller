<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Models\Customer;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The member box on the till: find a member to attach to the bill, or sign a
 * new one up without leaving the sale.
 */
class TillMemberController extends Controller
{
    public function __construct(protected LoyaltyService $loyalty) {}

    /**
     * A scanned card or a full mobile number resolves to one member; a name
     * or part of a number returns a short list to pick from.
     */
    public function lookup(Request $request): JsonResponse
    {
        $this->authorize('run-till');

        $term = trim($request->string('q')->toString());

        if (mb_strlen($term) < 2) {
            return response()->json(['match' => null, 'results' => [], 'error' => null]);
        }

        $found = $this->loyalty->findMember($term);

        if ($found['customer'] !== null || $found['error'] !== null) {
            return response()->json([
                'match' => $found['customer'] !== null ? $this->loyalty->presentForTill($found['customer']) : null,
                'results' => [],
                'error' => $found['error'],
            ]);
        }

        $results = Customer::query()
            ->with(['tier', 'activeCard'])
            ->active()
            ->search($term)
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn (Customer $customer): array => $this->loyalty->presentForTill($customer));

        return response()->json(['match' => null, 'results' => $results, 'error' => null]);
    }

    /**
     * Quick enrolment: name and mobile are enough to start earning on this bill.
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->loyalty->enrol(
            $request->safe()->only(['name', 'phone', 'birth_date', 'marketing_opt_in']),
            $request->user(),
        );

        return response()->json(['member' => $this->loyalty->presentForTill($customer)], 201);
    }
}
