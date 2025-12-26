<?php

namespace AwardWallet\ExtensionWorker;

class Text
{

    public static function cutString(string $text) : string
    {
        if (strlen($text) < 80) {
            return $text;
        }

        return substr($text, 0, 80) . "...";
    }
    
}