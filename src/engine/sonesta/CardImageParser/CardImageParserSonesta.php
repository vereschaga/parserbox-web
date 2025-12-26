<?php

namespace AwardWallet\Engine\sonesta\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;

class CardImageParserSonesta implements CardImageParserInterface
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
         * @CardExample(accountId=6544051, cardUuid="30fbc18a-46ac-4572-8649-825df108fc1e", groupId="format1")
         */
        $textFull = ($frontSide ? $frontSide->getText() . "\n" : '') . ($backSide ? $backSide->getText() : '');

        if (preg_match('/(?:\b|\D)(?<number>\d{9})(?:\b|\D)/', $textFull, $matches)) {
            // 102384251
            $result['Login'] = $matches['number'];
        }

        return $result;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Member # / Email
        ];
    }
}
