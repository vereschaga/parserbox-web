<?php

namespace AwardWallet\Engine\shangrila\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;

class CardImageParserShangrila implements CardImageParserInterface
{
    protected $devMode = 0;

    protected $frontSide;
    protected $backSide;

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        if (!$this->frontSide && !$this->backSide) {
            return [];
        }

        /**
         * @CardExample(accountId=630344, cardUuid="d3a1ad39-ab8d-4327-a762-91a102d66a07")
         * @CardExample(accountId=3480664, cardUuid="7be6ad5a-f107-41a0-9fc8-2aea88aabceb")
         */
        if ($properties = $this->parseFormat_1()) {
            return $properties;
        }

        return $properties;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login',
        ];
    }

    protected function parseFormat_1()
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        $textFront = $this->frontSide ? $this->frontSide->getText(3) : ''; // deviation: 3-4

        /**
         * @CardExample(accountId=3947158, cardUuid="1545d62f-4329-422c-b756-afbe59fd0289")
         */
        $textBack = $this->backSide ? $this->backSide->getText(6) : ''; // deviation: 1-11

        $textFull = $textFront . $textBack;

        // Number

        $in = ['.', ' ', 'S'];
        $out = ['',  '',  '5'];
        $textFrontConverted = str_replace($in, $out, $textFull);

        if (preg_match('/(?:\b|\D)(\d{12})(?:\b|\D)/', $textFrontConverted, $matches)) {
            $properties['Login'] = $matches[1];
        }

        return $properties;
    }
}
