<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'hotel_id',
        'room_id',
        'reported_by_user_id',
        'assigned_to_user_id',
        'status',
        'severity',
        'title',
        'description',
        'opened_at',
        'closed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
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
}
