<?php

namespace AwardWallet\Engine\klm\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\ImageRecognitionResult;

class CardImageParserKlm implements CardImageParserInterface
{
    protected $devMode = 0;

    /** @var ImageRecognitionResult */
    protected $frontSide;

    /** @var ImageRecognitionResult */
    protected $backSide;

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        if (!$this->frontSide && !$this->backSide) {
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
            'Login',
        ];
    }

    protected function parseFormat_1()
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        /**
         * @CardExample(accountId=3859917, cardUuid="72188a9f-1ffe-4111-8c25-fc850b5b2fc3")
         * @CardExample(accountId=3951820, cardUuid="67b9fdc5-5ed9-4db0-a5e7-bb4f51a8ead9")
         * @CardExample(accountId=3946870, cardUuid="94fe936e-51a7-4a59-ad17-1b0d99f636cf")
         * @CardExample(accountId=3524339, cardUuid="d1d2cce3-e19f-40ed-9c4e-090b95c24910")
         * @CardExample(accountId=3922652, cardUuid="7cf8d541-7172-4b82-81f0-7efd7394a024")
         * @CardExample(accountId=3106760, cardUuid="9b0721a5-ed30-4c8a-b525-bc4e08b16197")
         */
        $textFront = '';

        if (!empty($this->frontSide)) {
            $textFront = $this->frontSide->getText();
        } // deviation: default    OR    1-4

        // Number

        $textFrontConverted = str_replace('.', '', $textFront);

        // 03/2018
        $textFrontConverted = preg_replace('/\b\d{1,2}\/\d{1,4}/', '', $textFrontConverted);
        $text = $textFrontConverted;

        if (empty($text)) {
            $text = $this->backSide->getText();
        }

        preg_match_all('/^(\d[\d ]{7,}\d)[ ]*(?:\D|$)/m', $text, $numberMatches);

        foreach ($numberMatches[1] as $number) {
            $number = str_replace(' ', '', $number);

            if (preg_match('/^\d{10}$/', $number)) {
                $properties['Login'] = $number;
            } elseif (preg_match('/^\d{9}$/', $number)) {
                $properties['Login'] = $number;
            }
        }

        return $properties;
    }
}
