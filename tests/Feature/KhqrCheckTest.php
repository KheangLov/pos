<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\KhqrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesPosFixtures;
use Tests\TestCase;

/**
 * P1-5: the khqr:check-pending job confirms pending KHQR payments via Bakong
 * and must be a harmless no-op until the owner configures Bakong credentials
 * (payment method carrying a token) — no keys, no md5 → no API call.
 */
class KhqrCheckTest extends TestCase
{
    use CreatesPosFixtures;
    use RefreshDatabase;

    private function pendingKhqrPayment(bool $withToken = true, ?string $md5 = 'abc123'): array
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);
        $user = $this->makeUser($company, $branch);
        $table = $this->makeTable($this->makeFloorPlan($branch));
        $method = $this->makePaymentMethod($company, 'Bakong QR', 'khqr');

        if ($withToken) {
            $method->update(['bakong_token' => 'live-token']);
        }

        $invoice = Invoice::create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'table_id' => $table->id,
            'status' => 'pending',
            'subtotal' => 5.00,
            'discount_total' => 0,
            'tax_total' => 0,
            'total' => 5.50,
        ]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'payment_method_id' => $method->id,
            'method' => $method->name,
            'amount' => 5.50,
            'khqr_md5' => $md5,
            'status' => 'pending',
        ]);

        return compact('method', 'invoice', 'payment');
    }

    public function test_confirmed_payment_is_marked_successful_and_invoice_paid(): void
    {
        $f = $this->pendingKhqrPayment();

        $this->mock(KhqrService::class)
            ->shouldReceive('checkTransaction')
            ->once()
            ->with('live-token', 'abc123')
            ->andReturn(true);

        $this->artisan('khqr:check-pending')->assertSuccessful();

        $this->assertSame('successful', $f['payment']->fresh()->status);
        $this->assertSame('paid', $f['invoice']->fresh()->status);
    }

    public function test_unconfirmed_payment_stays_pending(): void
    {
        $f = $this->pendingKhqrPayment();

        $this->mock(KhqrService::class)
            ->shouldReceive('checkTransaction')
            ->once()
            ->andReturn(false);

        $this->artisan('khqr:check-pending')->assertSuccessful();

        $this->assertSame('pending', $f['payment']->fresh()->status);
        $this->assertSame('pending', $f['invoice']->fresh()->status);
    }

    public function test_no_api_call_without_a_bakong_token(): void
    {
        $f = $this->pendingKhqrPayment(withToken: false);

        $this->mock(KhqrService::class)->shouldReceive('checkTransaction')->never();

        $this->artisan('khqr:check-pending')->assertSuccessful();

        $this->assertSame('pending', $f['payment']->fresh()->status);
    }

    public function test_no_api_call_without_an_md5(): void
    {
        $f = $this->pendingKhqrPayment(md5: null);

        $this->mock(KhqrService::class)->shouldReceive('checkTransaction')->never();

        $this->artisan('khqr:check-pending')->assertSuccessful();

        $this->assertSame('pending', $f['payment']->fresh()->status);
    }
}
