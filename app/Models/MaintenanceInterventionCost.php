<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceInterventionCost extends Model
{
    use HasFactory;

    public const TYPE_LABOR = 'labor';

    public const TYPE_PARTS = 'parts';

    public const TYPE_TRANSPORT = 'transport';

    public const TYPE_SERVICE = 'service';

    public const TYPE_OTHER = 'other';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'hotel_id',
        'maintenance_intervention_id',
        'cost_type',
        'label',
        'quantity',
        'unit_price',
        'total_amount',
        'currency',
        'notes',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function intervention(): BelongsTo
    {
        return $this->belongsTo(MaintenanceIntervention::class, 'maintenance_intervention_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    protected static function booted(): void
    {
        static::saving(function (MaintenanceInterventionCost $cost): void {
            $quantity = (float) ($cost->quantity ?? 0);
            $unitPrice = (float) ($cost->unit_price ?? 0);

            $cost->total_amount = $quantity * $unitPrice;
        });
    }
}
