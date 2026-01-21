<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HousekeepingTask extends Model
{
    /** @use HasFactory<\Database\Factories\HousekeepingTaskFactory> */
    use HasFactory;

    use SoftDeletes;

    public const TYPE_CLEANING = 'cleaning';

    public const TYPE_INSPECTION = 'inspection';

    public const TYPE_REDO_CLEANING = 'redo-cleaning';

    public const TYPE_REDO_INSPECTION = 'redo-inspection';

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DONE = 'done';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public const PRIORITY_LOW = 'low';

    public const SOURCE_CHECKOUT = 'checkout';

    public const SOURCE_RECEPTION = 'reception';

    public const OUTCOME_PASSED = 'passed';

    public const OUTCOME_FAILED = 'failed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'hotel_id',
        'room_id',
        'type',
        'status',
        'priority',
        'created_from',
        'started_at',
        'ended_at',
        'duration_seconds',
        'outcome',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_seconds' => 'integer',
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

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'housekeeping_task_users', 'task_id', 'user_id')
            ->withPivot('joined_at', 'left_at');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(HousekeepingTaskChecklistItem::class, 'task_id');
    }
}
