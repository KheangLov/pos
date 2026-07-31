<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['name', 'business_type', 'email', 'phone', 'address', 'logo_url', 'is_active'])]
class Company extends Model
{
    use LogsActivity;

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
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
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
