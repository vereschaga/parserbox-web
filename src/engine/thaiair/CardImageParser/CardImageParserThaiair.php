<?php

namespace AwardWallet\Engine\thaiair\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;

class CardImageParserThaiair implements CardImageParserInterface
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
         * @CardExample(accountId=4737812, cardUuid="e25d549e-edec-4ef0-b4cc-6a1b9123d1e2", groupId="format1")
         * @CardExample(accountId=4663216, cardUuid="bcdadc14-b27c-4941-b59c-3c5e64e5892d", groupId="format1")
         * @CardExample(accountId=4592954, cardUuid="c3029fb6-76ec-400a-8b48-eec7e797f541", groupId="format1replacing")
         */
        $textFull = ($frontSide ? $frontSide->getText() . "\n" : '') . ($backSide ? $backSide->getText() : '');

        if (preg_match('/\b(?<number>[A-Z]{2}[ ]*[b\d]{5})(?:\b|\D)/', $textFull, $matches)) {
            // QB22085    |    NV 84893    |    MD1880b
            $result['Login'] = str_replace([' ', 'b'], ['', '6'], $matches['number']);
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
