<?php

namespace AwardWallet\Common\OneTimeCode;

class CommonProvider
{
    // refs #23031 otc emails from these pairs of providers are identical
    public static array $identicalProviders = [
        ['klm', 'airfrance', 'klbbluebiz'],
        ['etihad', 'etihadbusiness'],
        ['qantas', 'aquire'],
        ['qmiles', 'qatarbiz'],
    ];

    public static function getCodesList(string $code): array
    {
        foreach (self::$identicalProviders as $row) {
            if (in_array($code, $row)) {
                return $row;
            }
        }

        return [$code];
    }
}
