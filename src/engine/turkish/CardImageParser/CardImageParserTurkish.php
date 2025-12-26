<?php

namespace AwardWallet\Engine\turkish\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;

class CardImageParserTurkish implements CardImageParserInterface
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
         * @CardExample(accountId=3920190, cardUuid="9ddac810-67d2-4e7b-bb2f-6c175c427e24")
         * @CardExample(accountId=3785402, cardUuid="e900474d-16c3-45e6-bf55-2d4a240a707a")
         * @CardExample(accountId=3903482, cardUuid="ff5e2f5a-cf71-41d0-9a52-4f264e5c66c7")
         * @CardExample(accountId=3958461, cardUuid="a217dc42-6e7d-4d1d-8b4b-cbe8a54f6f92")
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

        $textFront = $this->frontSide ? $this->frontSide->getText(5) : ''; // deviation: 4-6

        $textBack = $this->backSide ? $this->backSide->getText() : ''; // deviation: default

        $textFull = $textFront . $textBack;

        // Number

        $textFrontConverted = str_replace(['.', ' '], '', $textFull);

        if (preg_match('/([Tt][Kk]|[Kk]|\b)(\d{9,10})(?:\D|\b)/', $textFrontConverted, $matches)) {
            $properties['Login'] = strtoupper($matches[1]) . $matches[2];
        }

        return $properties;
    }
}
