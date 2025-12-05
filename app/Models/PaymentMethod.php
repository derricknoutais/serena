<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'hotel_id',
        'name',
        'code',
        'type',
        'is_active',
        'is_default',
        'provider',
        'account_number',
        'config',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'config' => 'array',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
