<?php

namespace App\Helpers;

class IdHelper
{
    /**
     * Generates a random numeric string of length 16.
     *
     * Typically used for Stellar VPN, Antivirus, etc. when users do not want
     * to use their email address.
     *
     * The ID will never start with a leading zero.
     *
     * @return string
     */
    public static function makePrettyId(): string
    {
        $randomString = (string) rand(1, 9);

        for ($i = 1; $i < 16; $i++) {
            $randomString .= (string) rand(0, 9);
        }

        return $randomString;
    }
}
