<?php

namespace AwardWallet\Engine\solmelia\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;

class CardImageParserSolmelia implements CardImageParserInterface
{
    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $result = [];

        $frontSide = $cardRecognitionResult->getFront();
        $backSide = $cardRecognitionResult->getBack();

        if (!$frontSide && !$backSide) {
            return [];
        }

        $textFull = ($frontSide ? $frontSide->getText() . "\n" : '') . ($backSide ? $backSide->getText() : '');

        /**
         * @CardExample(accountId=1595209, cardUuid="245b0b25-9f97-482d-8be4-67fc99ed2a5d", groupId="format1")
         * @CardExample(accountId=4685535, cardUuid="f85ddb9e-a724-4bfb-a817-92c383b50914", groupId="format1")
         */
        if (preg_match('/^(?<number>\d{5,9}[A-Z])\b/m', $textFull, $matches)) {
            // 40673G    |    200523754H
            $result['Login'] = $matches['number'];
        }

        /**
         * @CardExample(accountId=4595312, cardUuid="ad312663-8324-438d-87a9-8311798b71bb", groupId="format2")
         */
        if (empty($result['Login']) && preg_match('/(?:\b|\D)(?<number>\d{9})(?:\b|\D)/', $textFull, $matches)) {
            // 000037092
            $result['Login'] = $matches['number'];
        }

        return $result;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // E-mail or Card Number
        ];
    }
}
