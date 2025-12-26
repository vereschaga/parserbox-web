<?php

namespace AwardWallet\Engine\national\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserNational implements CardImageParserInterface, CreditCardDetectorInterface
{
    protected $devMode = 0;

    protected $ccDetectionResult;

    protected $frontSide;
    protected $backSide;

    /**
     * @Detector(version="1")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        $this->ccDetectionResult = new CreditCardDetectionResult();

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        if (!$this->frontSide && !$this->backSide) {
            return $this->ccDetectionResult;
        }

        if ($this->detectCC_1()) {
            $this->hideCCNumber_1($this->ccDetectionResult);

            return $this->ccDetectionResult;
        }

        return $this->ccDetectionResult;
    }

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        if (!$this->frontSide && !$this->backSide) {
            return [];
        }

        /**
         * @CardExample(accountId=2534383, cardUuid="7badc328-2af0-488a-b20e-7dd3ac40a12e", groupId="format1reverseSides")
         */
        if ($this->frontSide && $this->backSide) {
            $patternBackSide = '/(?:' . implode('|', ['This\s*card\s*is\s*intended\s*for\s*my\s*exclusive\s*use', 'Member\s*Services']) . ')/i';

            if (preg_match($patternBackSide, $this->frontSide->getText())) { // deviation: default
                // reverse sides
                $this->frontSide = $cardRecognitionResult->getBack();
                $this->backSide = $cardRecognitionResult->getFront();
            }
        }

        /*
         * @CardExample(accountId=3933790, cardUuid="99203caf-fbb6-4705-9e7d-1c6aa636f3fa", groupId="format1")
         * @CardExample(accountId=3721378, cardUuid="6c52255f-c01e-4129-a2c5-bfd5ff7f4fed", groupId="format1")
         * @CardExample(accountId=3737072, cardUuid="9da26fdf-2ccb-4e18-a20e-fea5f37e0b17", groupId="format1")
         * @CardExample(accountId=3937502, cardUuid="9bb3f44e-ac2c-4ae0-a32e-c04c202a4647", groupId="format1")
         */
        if ($result = $this->parseFormat_1()) {
            return $result;
        }

        return $properties;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Emerald Club Number
        ];
    }

    protected function parseFormat_1()
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        $textFront = $this->frontSide ? $this->frontSide->getText() : ''; // deviation: default

        $textFrontConverted = str_replace('.', '', $textFront);

        /*
         * @CardExample(accountId=3848566, cardUuid="32394010-198a-4359-bd1c-e4f9b78d85e3", groupId="format1prefix")
         * @CardExample(accountId=3805795, cardUuid="ed9fd9f7-bf9b-44cd-8214-0c46be18a47f", groupId="format1prefix")
         */
        $patternPrefix = '(' . implode('|', ['NA', 'NE']) . ')';

        $pattern = '/'
            . '\b(?:' . $patternPrefix . '[ ]*)?(\d[\d ]{7,}\d)\b'	// Number
            . '/';

        if (preg_match($pattern, $textFrontConverted, $matches)) {
            $number = str_replace(' ', '', $matches[2]);

            if (!empty($matches[1])) {
                $properties['Login'] = $matches[1] . $number;
            } else {
                $properties['Login'] = $number;
            }
        }

        return $properties;
    }

    protected function detectCC_1(): bool
    {
        // example accounts: 4073123,1123763,3796866,3896638

        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $patterns = [
            'image'          => '/(?:VISA|MasterCard|American\s*Express|Credit\s*card|Citi)/i',
            'text'           => '/(?:\bVISA\b|MasterCard|AMERICAN\s*EXPRES|Bank\s*National|BANK\s*LIMITED)/i',
            'ccAllowedWords' => '/AMERICAN\s*EXPRESS?\s*-\s*EXECUTIVE/i', // AMERICAN EXPRESS - EXECUTIVE
            'ccShortNumber'  => '/\b(?:VISA|AMEX)\s*\(/i', // VISA (0613)    |    AMEX (1509)
        ];

        // FRONT

        if ($this->frontSide) {
            $frontLogos = $this->frontSide->getDOM(0)->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
            $frontLogoValues = array_values(array_filter($frontLogos));

            if (!empty($frontLogoValues[0])) {
                return true;
            }

            $textFront = $this->frontSide->getText(); // deviation: default
            $textFrontConverted = preg_replace($patterns['ccAllowedWords'], '', $textFront);
            $textFrontConverted = preg_replace($patterns['ccShortNumber'], '(', $textFrontConverted);

            if (preg_match($patterns['text'], $textFrontConverted)) {
                return true;
            }
        }

        // BACK

        if ($this->backSide) {
            $backLogos = $this->backSide->getDOM(0)->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
            $backLogoValues = array_values(array_filter($backLogos));

            if (!empty($backLogoValues[0])) {
                return true;
            }

            $textBack = $this->backSide->getText(); // deviation: default
            $textBackConverted = preg_replace($patterns['ccAllowedWords'], '', $textBack);
            $textBackConverted = preg_replace($patterns['ccShortNumber'], '(', $textBackConverted);

            if (preg_match($patterns['text'], $textBackConverted)) {
                return true;
            }
        }

        return false;
    }

    protected function hideCCNumber_1(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(0, 30, 100, 40), new Rectangle(30, 70, 40, 30)])
            ->setBack($rects);
    }
}
