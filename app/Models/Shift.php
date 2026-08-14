<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['branch_id', 'user_id', 'opening_amount', 'closing_amount', 'opened_at', 'closed_at', 'status'])]
class Shift extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'opening_amount' => 'decimal:2',
            'closing_amount' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cashNotes(): HasMany
    {
        return $this->hasMany(CashNote::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Successful revenue on this shift for a payment type (cash|card|khqr).
     *
     * Keyed on the linked PaymentMethod.type, NOT the free-text display
     * `method` column: an operator may call a method "Bakong QR" or "ABA",
     * which previously reconciled as zero (P0-1).
     *
     * Rows with no payment_method_id are cash by construction (both checkouts
     * fall back to method='cash'), so they are included in the cash bucket.
     */
    public function revenueForPaymentType(string $type): float
    {
        return (float) Payment::query()
            ->where('status', 'successful')
            ->whereHas('invoice', fn ($q) => $q->where('shift_id', $this->id))
            ->where(function ($q) use ($type) {
                $q->whereHas('paymentMethod', fn ($m) => $m->where('type', $type));

                if ($type === 'cash') {
                    $q->orWhere(function ($legacy) {
                        $legacy->whereNull('payment_method_id')->where('method', 'cash');
                    });
                }
            })
            ->sum('amount');
    }
}
