<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionEvent extends Model
{
    protected $table = 'subscription_events';

    protected $fillable = [
        'subscription_id',
        'user_id',
        'event_type',
        'event_source',
        'meta',
    ];

    protected $casts = [
        'meta'       => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id', 'id');
    }
}
