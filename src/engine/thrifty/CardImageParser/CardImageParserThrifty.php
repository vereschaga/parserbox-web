<?php

namespace AwardWallet\Engine\thrifty\CardImageParser;

use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;

class CardImageParserThrifty implements CardImageParserInterface
{
    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $result = [];

        $frontSide = $cardRecognitionResult->getFront();

        if (!$frontSide) {
            return [];
        }

        $textFull = ($frontSide ? $frontSide->getText() . "\n" : '');

        if (preg_match("/\n[#]?(?<number>[\dO]{10}\n)/u", $textFull, $matches)) {
            $result['Login'] = str_replace(" ", "", $matches['number']);
        } elseif (preg_match("/\n(?<number>[A-Z\d]{6}\n)/u", $textFull, $matches)) {
            if (preg_match("/\d/", $matches['number'])) {
                $result['Login'] = str_replace(" ", "", $matches['number']);
            }
        }

        return $result;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Member ID
        ];
    }
}
