<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceIntervention extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PAID = 'paid';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'hotel_id',
        'technician_id',
        'created_by_user_id',
        'started_at',
        'ended_at',
        'summary',
        'labor_cost',
        'parts_cost',
        'total_cost',
        'currency',
        'accounting_status',
        'submitted_to_accounting_at',
        'stock_location_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'submitted_to_accounting_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'paid_at' => 'datetime',
            'labor_cost' => 'decimal:2',
            'parts_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
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

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function costs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MaintenanceInterventionCost::class, 'maintenance_intervention_id');
    }

    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(MaintenanceTicket::class, 'maintenance_intervention_ticket')
            ->withPivot(['work_done', 'labor_cost', 'parts_cost'])
            ->withTimestamps();
    }

    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'stock_location_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaintenanceInterventionItem::class, 'maintenance_intervention_id');
    }

    public function recalcTotalsFromCosts(): void
    {
        $costs = $this->costs()->get();

        $laborTotal = (float) $costs
            ->where('cost_type', MaintenanceInterventionCost::TYPE_LABOR)
            ->sum('total_amount');
        $partsTotal = (float) $costs
            ->where('cost_type', MaintenanceInterventionCost::TYPE_PARTS)
            ->sum('total_amount');
        $grandTotal = (float) $costs->sum('total_amount');

        $this->labor_cost = $laborTotal;
        $this->parts_cost = $partsTotal;
        $this->total_cost = $grandTotal;

        $this->save();
    }

    protected static function booted(): void
    {
        static::saving(function (MaintenanceIntervention $intervention): void {
            $labor = (float) ($intervention->labor_cost ?? 0);
            $parts = (float) ($intervention->parts_cost ?? 0);

            $intervention->total_cost = $labor + $parts;
        });
    }
}
