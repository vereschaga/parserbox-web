<?php

namespace AwardWallet\Engine\lufthansa\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserLufthansa implements CardImageParserInterface, CreditCardDetectorInterface
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
         * @CardExample(accountId=3969317, cardUuid="971129b5-6c2f-4bbb-a63e-7b9d6f262e13", groupId="format1")
         */
        if ($properties = $this->parseFormat_1()) {
            return $properties;
        }

        return $properties;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // User name / Card Number
        ];
    }

    /**
     * @Detector(version="2")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        // example accounts: 3879470,4194789

        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $ccDetectionResult = new CreditCardDetectionResult();

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        if (!$this->frontSide && !$this->backSide) {
            return $ccDetectionResult;
        }

        $patterns = [
            'image' => '/(?:MasterCard|American\s*Express|Apple[ ]*Pay)/i',
            'text'  => '/(?:\bMas(?:t|i)er[ ]*Card\b|Cred?it\s*(?:C|G)ard\s*Service|This\s+card\s+is\s+issued\s+by)/i',
        ];

        // FRONT

        if ($this->frontSide) {
            $frontLogos = $this->frontSide->getDOM(0)->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
            $frontLogoValues = array_values(array_filter($frontLogos));

            if (!empty($frontLogoValues[0])) {
                $this->hideCCNumber($ccDetectionResult);
            }

            $textFront = $this->frontSide->getText(); // deviation: default

            if (preg_match($patterns['text'], $textFront)) {
                $this->hideCCNumber($ccDetectionResult);
            }
        }

        // BACK

        if ($this->backSide) {
            $backLogos = $this->backSide->getDOM(0)->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
            $backLogoValues = array_values(array_filter($backLogos));

            if (!empty($backLogoValues[0])) {
                $this->hideCCNumber($ccDetectionResult);
            }

            $textBack = $this->backSide->getText(); // deviation: default

            if (preg_match($patterns['text'], $textBack)) {
                $this->hideCCNumber($ccDetectionResult);
            }
        }

        return $ccDetectionResult;
    }

    protected function parseFormat_1()
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        $textFront = $this->frontSide ? $this->frontSide->getText() : ''; // deviation: default

        $textFrontConverted = str_replace(['.', ' '], '', $textFront);

        if (stripos($textFrontConverted, 'mastercard') !== false) {
            return [];
        }

        // Number

        /**
         * @CardExample(accountId=3876729, cardUuid="59a749e5-a986-4f06-8f22-12bc59ff96d9", groupId="format1")
         * @CardExample(accountId=3869697, cardUuid="52f65619-ac92-482b-906c-f7755cd6ffe2", groupId="format1")
         * @CardExample(accountId=3887718, cardUuid="1da6c067-0172-4e53-9e2b-257e281a6b19", groupId="format1")
         * @CardExample(accountId=3933291, cardUuid="fcab3fc7-a50f-45d5-83fb-58405767c05b", groupId="format1")
         */
        $in = ['D', 'O', 'i', 'J', 'L', 'l', '+', 'b', 'A', 'a'];
        $out = ['0', '0', '1', '1', '1', '1', '4', '6', '9', '9'];
        $textFrontConverted = str_replace($in, $out, $textFrontConverted);

        if (preg_match('/\b(?<number>\d{15})\b/', $textFrontConverted, $matches)) {
            $properties['Login'] = $matches['number'];
        }

        return $properties;
    }

    protected function hideCCNumber(CreditCardDetectionResult &$ccDetectionResult)
    {
        return $ccDetectionResult
            ->setFront($rect = [new Rectangle(0, 40, 100, 60)])
            ->setBack($rect);
    }
}
