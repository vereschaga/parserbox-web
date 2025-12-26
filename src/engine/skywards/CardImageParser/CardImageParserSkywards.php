<?php

namespace AwardWallet\Engine\skywards\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;

class CardImageParserSkywards implements CardImageParserInterface
{
    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $result = [];

        $front = $cardRecognitionResult->getFront();
        $textFront = $front ? str_replace('.', '', $front->getText()) : '';

        $back = $cardRecognitionResult->getBack();
        $textBack = $back ? str_replace('.', '', $back->getText()) : '';

        if (!$textFront && !$textBack) {
            return [];
        }

        if ($result = $this->parseFormat_1($textFront, $textBack)) {
            return $result;
        }

        return $result;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Email Address or Emirates Skywards Number
        ];
    }

    private function parseFormat_1($textFront = '', $textBack = '')
    {
        $properties = [];

        $textFull = $textFront . "\n" . $textBack;

        /**
         * @CardExample(accountId=221480, cardUuid="67587b8b-3239-40f6-b085-4eb293d2cc0e", groupId="format1")
         * @CardExample(accountId=3449153, cardUuid="22d9bf39-b1f1-4a5b-a0dc-d922db95c86b", groupId="format1")
         */
        $patternPrefix = '(?<prefix>' . implode('|', ['EK']) . ')';
        $pattern = '/'
            . '\b' . $patternPrefix . '[ ]*(?<number>\d[\d ]{7,}\d)\b'
            . '/';

        if (preg_match($pattern, $textFull, $m)) {
            $properties['Login'] = $m['prefix'] . str_replace(' ', '', $m['number']);
        }

        return $properties;
    }
}
