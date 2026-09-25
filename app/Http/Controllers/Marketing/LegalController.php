<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * The pages a customer — and a payment provider — expects to find before
 * handing over money: what is agreed, what is kept, and how to get hold of
 * someone.
 */
class LegalController extends Controller
{
    public function terms(): View
    {
        return view('marketing.legal.terms', $this->details());
    }

    public function privacy(): View
    {
        return view('marketing.legal.privacy', $this->details());
    }

    public function refunds(): View
    {
        return view('marketing.legal.refunds', $this->details());
    }

    public function contact(): View
    {
        return view('marketing.legal.contact', $this->details());
    }

    /**
     * @return array<string, mixed>
     */
    protected function details(): array
    {
        $legal = config('tenancy.legal');

        return [
            'platform' => config('tenancy.platform_name'),
            'entity' => $legal['entity'] ?: config('tenancy.platform_name'),
            'legal' => $legal,
            'trialDays' => config('tenancy.trial_days'),
            'keepArchivedDays' => Store::KEEP_ARCHIVED_DAYS,
            'updatedOn' => Carbon::parse($legal['updated_on']),
        ];
    }
}
