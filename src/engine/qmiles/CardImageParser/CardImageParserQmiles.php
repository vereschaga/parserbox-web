<?php

namespace AwardWallet\Engine\qmiles\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;

class CardImageParserQmiles implements CardImageParserInterface
{
    protected $devMode = 0;

    protected $frontSide;
    protected $backSide;

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        $this->frontSide = $cardRecognitionResult->getFront();

        if (!$this->frontSide) {
            return [];
        }

        /**
         * @CardExample(accountId=3894376, cardUuid="8bf799b3-6a02-41c3-8edf-fde9819c3a4e")
         * @CardExample(accountId=833176, cardUuid="f6b65195-ba8d-43d9-9369-1fd11c21a2e2")
         * @CardExample(accountId=2526865, cardUuid="2c6b1d30-bb43-49c2-9faf-be930458a7ae")
         * @CardExample(accountId=3689202, cardUuid="6db1c225-356f-46d6-a967-051e576ea3de")
         * @CardExample(accountId=221486, cardUuid="9456a421-b06b-4284-a7f7-90ac0f91e912")
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

        $textFront = $this->frontSide->getText(3); // deviation: 2-5

        // Number

        $textFrontConverted = str_replace(['.', ' '], '', $textFront);

        if (preg_match('/(?:\b|\D)(\d{9,10})(?:\b|\D)/', $textFrontConverted, $matches)) {
            $properties['Login'] = $matches[1];
        }

        return $properties;
    }
}
