<?php

namespace AwardWallet\Engine\gulfair\CardImageParser;

use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;

class CardImageParserGulfair implements CardImageParserInterface
{
    private $frontSide;
    private $backSide;

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        if (!$this->frontSide) {
            return [];
        }

        if ($properties = $this->parseFormat_1()) {
            return $properties;
        }

        return $properties;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Name',
            'Login',
        ];
    }

    private function parseFormat_1()
    {
        $properties = [];

        $textFront = $this->frontSide ? $this->frontSide->getText() : '';
        $textBack = $this->backSide ? $this->backSide->getText() : '';

        /**
         * @CardExample(accountId=5663501, cardUuid="c2d81d38-f17f-4665-9142-91be43041cdb", groupId="format1")
         */
        if (preg_match('/\n*(?<name>[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\n(?<number>\d{8})\nExpires/u', $textFront, $matches)) {
            $properties['Name'] = $matches['name'];
            $properties['Login'] = $matches['number'];
        }

        return $properties;
    }
}
