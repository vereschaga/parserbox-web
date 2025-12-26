<?php

namespace AwardWallet\Engine\porter\CardImageParser;

use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;

class CardImageParserPorter implements CardImageParserInterface
{
    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $result = [];

        $frontSide = $cardRecognitionResult->getFront();
        $backSide = $cardRecognitionResult->getBack();

        if (!$frontSide && !$backSide) {
            return [];
        }

        /**
         * @CardExample(accountId=5209329, cardUuid="") *
         */
        $textFull = ($frontSide ? $frontSide->getText() . "\n" : '') . ($backSide ? $backSide->getText() : '');

        if (preg_match('/(?:Member\D+)?(\d{3}\s*\-*\d{3}\s*\-*\d{4})\b/i', $textFull, $matches)) {
            $result['Login'] = str_replace([' ', '-'], '', $matches[1]);
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
