<?php

namespace App;

final class SubscriptionEventType
{
    public const VPN_LOGIN = 'VPN_LOGIN';
    public const VPN_CONNECTED = 'VPN_CONNECTED';
    public const VPN_SESSION_STARTED = 'VPN_SESSION_STARTED';
    public const VPN_SESSION_ENDED = 'VPN_SESSION_ENDED';
    public const APP_OPENED = 'APP_OPENED';
    public const PLAN_ACTIVATED = 'PLAN_ACTIVATED';
    public const PAYMENT_SUCCEEDED = 'PAYMENT_SUCCEEDED';
    public const PAYMENT_FAILED = 'PAYMENT_FAILED';

    public static function normalize(?string $eventType): ?string
    {
        if ($eventType === null || trim($eventType) === '') {
            return null;
        }

        return strtoupper(trim($eventType));
    }
}
