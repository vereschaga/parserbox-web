<?php


namespace AwardWallet\Engine\testprovider\RewardAvailability;


class Credentials
{

    public static function getCredentials(): array
    {
        if (file_exists($file = __DIR__ . '/credentials') && strpos($line = file_get_contents($file), ':') !== false) {
            return ['Login' => ($parts = explode(':', $line))[0], 'Pass' => $parts[1]];
        } else {
            return [];
        }
    }

}