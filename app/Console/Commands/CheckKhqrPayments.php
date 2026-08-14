<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\KhqrService;
use Illuminate\Console\Command;

/**
 * P1-5: poll Bakong for pending KHQR payments and mark confirmed ones paid.
 *
 * Gated by design: only payments that carry a generated khqr_md5 AND whose
 * payment method holds a live Bakong token can be verified. Until the owner
 * adds Bakong credentials (renewed into PaymentMethod::bakong_token) this is
 * a no-op, and the existing manual confirmations keep working unchanged.
 *
 * Deliberately conservative: checkTransaction() never returns a hard "no" —
 * anything that isn't a confirmed success keeps the payment pending (an
 * unknown state must never be treated as a failure).
 */
class CheckKhqrPayments extends Command
{
    protected $signature = 'khqr:check-pending';

    protected $description = 'Mark pending KHQR payments successful when Bakong confirms them';

    public function handle(KhqrService $khqr): int
    {
        $pending = Payment::query()
            ->where('status', 'pending')
            ->whereNotNull('khqr_md5')
            ->whereHas('paymentMethod', fn ($q) => $q->where('type', 'khqr')->whereNotNull('bakong_token'))
            ->with('paymentMethod')
            ->get();

        $verified = 0;

        foreach ($pending as $payment) {
            $token = $payment->paymentMethod->bakong_token; // encrypted cast → plain text

            if (! $khqr->checkTransaction($token, $payment->khqr_md5)) {
                continue;
            }

            $payment->update(['status' => 'successful']);
            $payment->invoice?->syncPaidStatus();
            $verified++;
        }

        if ($verified > 0) {
            $this->info("Verified {$verified} KHQR payment(s).");
        }

        return self::SUCCESS;
    }
}
