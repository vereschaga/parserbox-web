<?php

namespace AwardWallet\Engine\malaysia\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;

class CardImageParserMalaysia implements CardImageParserInterface
{
    private $devMode = 0;

    private $frontSide;
    private $backSide;

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        $this->frontSide = $cardRecognitionResult->getFront();

        if (!$this->frontSide) {
            return [];
        }

        /*
         * @CardExample(accountId=1580996, cardUuid="052f1774-c6e2-458c-8b6d-c65f1c3ee09c", groupId="format1")
         * @CardExample(accountId=3946768, cardUuid="8a6818ef-56d4-464e-9392-d9866d6bd6ac", groupId="format1")
         * @CardExample(accountId=3898022, cardUuid="985cab93-f04b-4311-b982-aaa44259b9c1", groupId="format1")
         * @CardExample(accountId=3906842, cardUuid="1fd75106-aaba-47db-9ff7-82a315aae83c", groupId="format1")
         * @CardExample(accountId=3485424, cardUuid="85dc8e26-21be-40d7-8f50-a3e7427dd3ae", groupId="format1")
         */
        if ($result = $this->parseFormat_1()) {
            return $result;
        }

        return $properties;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Membership Number
        ];
    }

    private function parseFormat_1()
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        $textFront = $this->frontSide->getText(); // deviation: default

        /**
         * @CardExample(accountId=4150022, cardUuid="dc838c9f-7caa-4f97-a330-b752d5497312", groupId="format1BackgroundChars")
         */
        $textFrontConverted = str_replace(['.', ' ', 'E'], '', $textFront); // remove background chars
        $textFrontConverted = str_replace(['М', 'Н', '拄'], ['M', 'H', 'H'], $textFrontConverted); // replace chars [ru,zh] -> en

        /**
         * @CardExample(accountId=4267445, cardUuid="a08e8a5a-5534-438d-b005-3aa7b4ce7a6d", groupId="format1ReplaceDigits")
         */
        $textFrontConverted = str_replace(['O', 'o', 'S', 's'], ['0', '0', '5', '5'], $textFrontConverted); // replace chars [A-z] -> [0-9]

        /**
         * @CardExample(accountId=3857491, cardUuid="50db28d7-dc00-4f29-94ee-ffc8beea0e66", groupId="format1prefix")
         */
        $patternPrefix = '(?:' . implode('|', ['MH', 'H']) . ')';

        /**
         * @CardExample(accountId=1283113, cardUuid="41f67ae9-7e33-4a12-8669-62d06ffb0cbb", groupId="format1TenDigits")
         */
        $pattern = '/'
            . '\b(?<number>' . $patternPrefix . '\d{9,10})\b' // Number
            . '/';

        if (preg_match($pattern, $textFrontConverted, $matches)) {
            $properties['Login'] = $matches['number'];
        }

        return $properties;
    }
}
