<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['name', 'business_type', 'email', 'phone', 'address', 'logo_url', 'is_active', 'requires_2fa', 'ai_assistant_enabled', 'anthropic_api_key'])]
#[Hidden(['anthropic_api_key'])]
class Company extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'requires_2fa' => 'boolean',
            'ai_assistant_enabled' => 'boolean',
            'anthropic_api_key' => 'encrypted',
        ];
    }

    /**
     * Single source of truth for the business-type options — used by
     * CompanyForm's Select, CompaniesTable's badge, and the serial-number
     * gating below, so the list only ever needs updating in one place.
     *
     * @return array<string, string>
     */
    public static function businessTypes(): array
    {
        return [
            'coffee_shop' => 'Coffee Shop',
            'restaurant' => 'Restaurant',
            'pub' => 'Pub',
            'bar' => 'Bar',
            'clothes_shop' => 'Clothes Shop',
            'computer_phone_shop' => 'Computer / Phone Shop',
        ];
    }

    /**
     * Business types that sell individually-trackable, serialised goods
     * (IMEIs, device serials). Everything else — coffee, food, drinks — has
     * no use for per-unit serial tracking, so the feature stays hidden for
     * them rather than cluttering the catalogue with an irrelevant toggle.
     */
    public function usesSerialNumbers(): bool
    {
        return $this->business_type === 'computer_phone_shop';
    }

    public function getActivitylogOptions(): LogOptions
    {
        // anthropic_api_key is a live API credential - never write it into
        // the audit trail, same reasoning as PaymentMethod::bakong_token.
        return LogOptions::defaults()->logFillable()->logExcept(['anthropic_api_key'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    /**
     * `logo_url` stores a disk-relative path (Filament's FileUpload manages
     * it that way) - resolve it to a real URL for display.
     */
    public function logoUrl(): ?string
    {
        return $this->logo_url ? Storage::disk('minio')->url($this->logo_url) : null;
    }
}
