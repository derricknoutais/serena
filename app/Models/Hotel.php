<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Hotel extends Model
{
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',

        'currency',
        'timezone',
        'address',
        'city',
        'country',
        'check_in_time',
        'check_out_time',
        'stay_settings',
        'document_settings',
        'business_day_start_time',
        'business_day_timezone',
        'default_bar_stock_location_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stay_settings' => 'array',
            'document_settings' => 'array',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDocumentSettingsAttribute($value): array
    {
        $decoded = [];

        if (is_array($value)) {
            $decoded = $value;
        } elseif (is_string($value)) {
            $decoded = json_decode($value, true) ?? [];
        }

        $addressParts = array_filter([
            $this->address,
            $this->city,
            $this->country,
        ]);

        $defaults = [
            'display_name' => $this->name,
            'contact' => [
                'address' => trim(implode(', ', $addressParts)),
                'phone' => null,
                'email' => null,
            ],
            'legal' => [
                'nif' => null,
                'rccm' => null,
            ],
            'header_text' => null,
            'footer_text' => null,
            'logo_path' => null,
            'logo_url' => null,
        ];

        $settings = array_replace_recursive($defaults, $decoded);
        $logoPath = $settings['logo_path'] ?? null;

        if ($logoPath) {
            $disk = config('filesystems.document_logos_disk', 'public');
            $settings['logo_url'] = Storage::disk($disk)->url($logoPath);
        }

        return $settings;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(RoomType::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function folios(): HasMany
    {
        return $this->hasMany(Folio::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }

    public function productCategories(): HasMany
    {
        return $this->hasMany(ProductCategory::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function defaultBarStockLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'default_bar_stock_location_id');
    }
}
