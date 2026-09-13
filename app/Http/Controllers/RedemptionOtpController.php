<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\LoyaltyRedemptionOtp;
use App\Services\WhatsAppGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The code a member gives the cashier before their points are spent.
 *
 * The till asks for a code, the member reads it off their own phone, and the
 * cashier types it back. Someone who finds a loyalty card cannot spend what is
 * on it without the member's handset.
 */
class RedemptionOtpController extends Controller
{
    public function __construct(protected WhatsAppGateway $whatsapp) {}

    /**
     * Send a code to the member's WhatsApp.
     */
    public function send(Request $request): JsonResponse
    {
        $this->authorize('run-till');

        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'points' => ['required', 'integer', 'min:1', 'max:10000000'],
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);

        if (! $this->whatsapp->enabled()) {
            return response()->json([
                'sent' => false,
                'message' => 'WhatsApp is not set up, so no code can be sent.',
            ], 422);
        }

        if (blank($customer->phone)) {
            return response()->json([
                'sent' => false,
                'message' => "{$customer->name} has no mobile number on file.",
            ], 422);
        }

        $to = $this->whatsapp->toE164($customer->phone);
        ['otp' => $otp, 'code' => $code] = LoyaltyRedemptionOtp::issueFor(
            $customer, (int) $validated['points'], $request->user(), $to,
        );

        $message = $this->whatsapp->sendOtp($to, $code, $customer, $request->user());

        if ($message === null || $message->hasFailed()) {
            // Nothing was delivered, so the code is worthless — close it off
            // rather than leave it live for the attempt limit to burn down.
            $otp->forceFill(['consumed_at' => now()])->save();

            return response()->json([
                'sent' => false,
                'message' => 'WhatsApp would not deliver the code. An owner can approve this redemption instead.',
            ], 422);
        }

        return response()->json([
            'sent' => true,
            'otp_id' => $otp->id,
            'sent_to' => $this->whatsapp->mask($to),
            'expires_in' => (int) config('services.whatsapp.otp_minutes', 5),
            'message' => "Code sent to {$this->whatsapp->mask($to)}.",
        ]);
    }

    /**
     * Check the code the member read out.
     */
    public function verify(Request $request): JsonResponse
    {
        $this->authorize('run-till');

        $validated = $request->validate([
            'otp_id' => ['required', 'integer', 'exists:loyalty_redemption_otps,id'],
            'code' => ['required', 'string', 'max:10'],
        ]);

        $otp = LoyaltyRedemptionOtp::findOrFail($validated['otp_id']);

        if (! $otp->isUsable()) {
            return response()->json([
                'verified' => false,
                'message' => 'That code has expired or been used. Send a new one.',
            ], 422);
        }

        if (! $otp->verify($validated['code'])) {
            $left = max(0, LoyaltyRedemptionOtp::MAX_ATTEMPTS - $otp->fresh()->attempts);

            return response()->json([
                'verified' => false,
                'message' => $left > 0
                    ? "That code is wrong — {$left} attempt(s) left."
                    : 'Too many wrong attempts. Send a new code.',
            ], 422);
        }

        return response()->json([
            'verified' => true,
            'otp_id' => $otp->id,
            'message' => 'Code confirmed. The points can be redeemed on this bill.',
        ]);
    }

    /**
     * The owner approves without a code, because it could not be delivered.
     */
    public function override(Request $request): JsonResponse
    {
        // manage-customers would let any active cashier through — it is only
        // is_active. Approving a redemption without the member's say-so is the
        // owner's call, so the gate has to be the one that means that.
        $this->authorize('manage-subscription');

        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'points' => ['required', 'integer', 'min:1', 'max:10000000'],
            'reason' => ['required', 'string', 'min:3', 'max:200'],
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);

        $otp = LoyaltyRedemptionOtp::overrideFor(
            $customer, (int) $validated['points'], $request->user(), $validated['reason'],
        );

        return response()->json([
            'verified' => true,
            'otp_id' => $otp->id,
            'message' => 'Approved without a code. This is recorded against the bill.',
        ]);
    }
}
