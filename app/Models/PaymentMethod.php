<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['company_id', 'branch_id', 'name', 'type', 'account_details', 'merchant_id', 'acquiring_bank', 'bakong_token', 'currency', 'is_active'])]
class PaymentMethod extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        // bakong_token is a live API credential - never write it into the
        // audit trail, same reasoning as Telescope's password redaction.
        return LogOptions::defaults()->logFillable()->logExcept(['bakong_token'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'bakong_token' => 'encrypted',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
