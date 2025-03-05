<?php

namespace App\Helpers;

class IdHelper
{

    /**
     * Generates a random string of length 16 - with only numbers.
     * Typically we use this for Stellar VPN, Antivirus etc, if users dont want,
     * to use their email.
     * @return string
     */
    public static function makePrettyId(): string {
        $characters = '0123456789';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < 16; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }

}
