<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\SubscriptionEventType;

class SubscriptionEventLogger
{
    public function log(
        ?string $subscriptionId,
        string $eventType,
        ?int $userId = null,
        ?string $eventSource = null,
        ?array $meta = null
    ): SubscriptionEvent {
        $subscription = null;

        if ($subscriptionId !== null) {
            $subscription = Subscription::find($subscriptionId);
        }

        if ($userId === null && $subscription !== null) {
            $userId = $subscription->user_id;
        }

        return SubscriptionEvent::create([
            'subscription_id' => $subscriptionId,
            'user_id'         => $userId,
            'event_type'      => SubscriptionEventType::normalize($eventType),
            'event_source'    => $eventSource,
            'meta'            => $meta,
        ]);
    }
}
