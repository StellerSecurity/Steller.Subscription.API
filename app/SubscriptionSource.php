<?php

namespace App;

final class SubscriptionSource
{
    public const ESIM_PURCHASE = 'ESIM_PURCHASE';
    public const DIRECT_VPN_PURCHASE = 'DIRECT_VPN_PURCHASE';
    public const DIRECT_ANTIVIRUS_PURCHASE = 'DIRECT_ANTIVIRUS_PURCHASE';
    public const AFFILIATE_VPN_PURCHASE = 'AFFILIATE_VPN_PURCHASE';
    public const ADMIN_GRANTED = 'ADMIN_GRANTED';
    public const PROMO_GRANTED = 'PROMO_GRANTED';

    public static function all(): array
    {
        return [
            self::ESIM_PURCHASE,
            self::DIRECT_VPN_PURCHASE,
            self::DIRECT_ANTIVIRUS_PURCHASE,
            self::AFFILIATE_VPN_PURCHASE,
            self::ADMIN_GRANTED,
            self::PROMO_GRANTED,
        ];
    }

    public static function normalize(?string $source): ?string
    {
        if ($source === null || trim($source) === '') {
            return null;
        }

        return strtoupper(trim($source));
    }
}
