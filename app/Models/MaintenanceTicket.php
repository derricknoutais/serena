<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MaintenanceTicket extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const SEVERITY_LOW = 'low';

    public const SEVERITY_MEDIUM = 'medium';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_CRITICAL = 'critical';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'hotel_id',
        'room_id',
        'maintenance_type_id',
        'reported_by_user_id',
        'assigned_to_user_id',
        'closed_by_user_id',
        'status',
        'severity',
        'blocks_sale',
        'title',
        'description',
        'opened_at',
        'resolved_at',
        'closed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'blocks_sale' => 'boolean',
        ];
    }

    public static function defaultBlocksSaleFromSeverity(?string $severity): bool
    {
        return in_array($severity, [self::SEVERITY_HIGH, self::SEVERITY_CRITICAL], true);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(MaintenanceType::class, 'maintenance_type_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function interventions(): BelongsToMany
    {
        return $this->belongsToMany(MaintenanceIntervention::class, 'maintenance_intervention_ticket')
            ->withPivot(['work_done', 'labor_cost', 'parts_cost'])
            ->withTimestamps();
    }
}
