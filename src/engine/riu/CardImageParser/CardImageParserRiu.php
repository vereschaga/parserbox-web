<?php

namespace AwardWallet\Engine\riu\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;

class CardImageParserRiu implements CardImageParserInterface
{
    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $frontSide = $cardRecognitionResult->getFront();
        $backSide = $cardRecognitionResult->getBack();

        if (!$frontSide && !$backSide) {
            return [];
        }

        /**
         * @CardExample(accountId=6471217, cardUuid="5e90853d-b9c0-4392-8fad-e3f1bdc65bae", groupId="format1")
         */
        $textFull = ($frontSide ? $frontSide->getText() . "\n" : '') . ($backSide ? $backSide->getText() : '');

        if (preg_match('/RIU\nHotels & Resorts\n(?<name>[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\n(?<number>\d+)\nclass/', $textFull, $matches)) {
            $result['Login'] = $matches['number'];
            $result['Name'] = $matches['name'];
        }

        return $result;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login',
            'Name',
        ];
    }
}
