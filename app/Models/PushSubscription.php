<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NotificationChannels\WebPush\PushSubscription as BasePushSubscription;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class PushSubscription extends BasePushSubscription
{
    /** @use HasFactory<\Database\Factories\PushSubscriptionFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'endpoint',
        'public_key',
        'auth_token',
        'content_encoding',
        'user_agent',
        'last_seen_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
