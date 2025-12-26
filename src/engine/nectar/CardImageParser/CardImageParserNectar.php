<?php

namespace AwardWallet\Engine\nectar\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;

class CardImageParserNectar implements CardImageParserInterface
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
         * @CardExample(accountId=3947895, cardUuid="1aa6ce88-4d97-41f0-a26c-34d5f95f564c", groupId="format1")
         * @CardExample(accountId=4134241, cardUuid="f687bed9-2730-4b4e-a5d7-367eb22c9eec", groupId="format1")
         * @CardExample(accountId=3890406, cardUuid="4b93155b-06db-4866-973f-f6e86bdd7373", groupId="format1")
         */
        if ($properties = $this->parseFormat_1()) {
            return $properties;
        }

        return $properties;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Card Number (Last 11 digits)
        ];
    }

    protected function parseFormat_1()
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        $patterns = [
            'number' => '/\d(\d{11})(?:\b|\D)/',
        ];

        // BACK

        $textBack = $this->backSide ? $this->backSide->getText() : ''; // deviation: default    |    0-8

        /**
         * @CardExample(accountId=3734583, cardUuid="9885a113-0f21-47cc-befe-55c81c8f21f3", groupId="format1")
         */
        $in = ['O', 'o'];
        $out = ['0', '0'];
        $textBackConverted = str_replace($in, $out, $textBack);

        $textBackConverted = str_replace(['.', ' ', '"'], '', $textBackConverted);

        $textBackConverted = preg_replace('/(?:Call|line)\s*\d+/i', '', $textBackConverted); // remove phone numbers

        if (preg_match($patterns['number'], $textBackConverted, $matches)) {
            $properties['Login'] = $matches[1];

            return $properties;
        }

        // FRONT

        $textFront = $this->frontSide ? $this->frontSide->getText(5) : ''; // deviation: 1-10

        $in = ['O', 'o', 'b'];
        $out = ['0', '0', '6'];
        $textFrontConverted = str_replace($in, $out, $textFront);

        $textFrontConverted = str_replace(['.', ' '], '', $textFrontConverted);

        $textFrontConverted = preg_replace('/(?:\b|\D)9?8?26300/', '', $textFrontConverted); // remove default prefixes (without last zero)

        if (preg_match($patterns['number'], $textFrontConverted, $matches)) {
            $properties['Login'] = $matches[1];
        }

        return $properties;
    }
}
