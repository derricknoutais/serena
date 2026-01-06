<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HousekeepingChecklist extends Model
{
    /** @use HasFactory<\Database\Factories\HousekeepingChecklistFactory> */
    use HasFactory;

    public const SCOPE_GLOBAL = 'global';

    public const SCOPE_ROOM_TYPE = 'room_type';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'hotel_id',
        'name',
        'scope',
        'room_type_id',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(HousekeepingChecklistItem::class, 'checklist_id')
            ->orderBy('sort_order');
    }
}
