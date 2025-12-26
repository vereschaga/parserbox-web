<?php

namespace AwardWallet\Engine\velocity\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserVelocity implements CardImageParserInterface, CreditCardDetectorInterface
{
    protected $devMode = 0;

    protected $ccDetectionResult;

    protected $frontSide;
    protected $backSide;

    /**
     * @Detector(version="2")
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
         * @CardExample(accountId=3801429, cardUuid="a97d15f7-84a5-4a56-b7bf-895badb3bc43", groupId="format1")
         * @CardExample(accountId=3854209, cardUuid="f3b7c1ef-79b5-41d0-9b90-0210b46a9396", groupId="format1")
         * @CardExample(accountId=2823873, cardUuid="7e59294f-c51f-40a0-8ab5-f31e27191355", groupId="format1")
         * @CardExample(accountId=1031795, cardUuid="6c37a23e-aeab-47a5-9d95-12c23a653ea1", groupId="format1")
         * @CardExample(accountId=47366, cardUuid="5f841ce7-1a34-41e6-bd60-08b6d2b43808", groupId="format1")
         */
        if ($properties = $this->parseFormat_1()) {
            return $properties;
        }

        return $properties;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Member No
        ];
    }

    protected function parseFormat_1()
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        $textFront = $this->frontSide ? $this->frontSide->getDOM(2)->getTextRectangle(0, 0, 10, 20) : ''; // deviation: 1-4

        $textFrontConverted = str_replace(['.', ' '], '', $textFront);

        if (stripos($textFrontConverted, 'NewZealand') !== false) {
            $textFront = '';
        } // remove front side content

        $textBack = $this->backSide ? $this->backSide->getDOM(2)->getTextRectangle(0, 0, 10, 20) : ''; // deviation: just as $textFront

        $textBackConverted = str_replace(['.', ' '], '', $textBack);

        if (stripos($textBackConverted, 'NewZealand') !== false) {
            $textBack = '';
        } // remove back side content

        $textFull = $textFront . "\n" . $textBack;

        // Number

        /**
         * @CardExample(accountId=3935408, cardUuid="4e917037-56e8-403d-9cd2-9068665d46d2", groupId="format1")
         */
        $textFullConverted = preg_replace('/\bNO\b/i', '', $textFull);

        /**
         * @CardExample(accountId=2032227, cardUuid="d9d1810a-c162-4fda-8ff4-0b4696c88966", groupId="format1")
         * @CardExample(accountId=3994149, cardUuid="92c92e01-48ab-4e7e-ab28-35d9589b24d9", groupId="format1")
         */
        $in = ['O', 'S', 'b'];
        $out = ['0', '5', '6'];
        $textFullConverted = str_replace($in, $out, $textFullConverted);

        $textFullConverted = str_replace(['.', ' '], '', $textFullConverted);

        $textFullConverted = preg_replace('/[-+]\d+/', '', $textFullConverted); // remove phone numbers

        if (preg_match('/(?:\b|\D)(\d{10,11})(?:\b|\D)/', $textFullConverted, $matches)) {
            $properties['Login'] = $matches[1];
        }

        return $properties;
    }

    protected function detectCC_1(): bool
    {
        // example accounts: 4003053,3371683,4024606,4052448,3793817,2032227,4152222

        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $patterns = [
            'image' => '/(VISA|MasterCard)/i',
            'text'  => '/(?:\bVISA\b|MasterCard|americanexpress|AMEX|Global Wallet|globalwallet\.com)/i',
        ];

        $textFull = '';

        // FRONT

        if ($this->frontSide) {
            $frontLogos = $this->frontSide->getDOM(0)->findNodes('/img[@left > 50]/@alt', null, $patterns['image']);
            $frontLogoValues = array_values(array_filter($frontLogos));

            if (!empty($frontLogoValues[0])) {
                return true;
            }

            $textFront = $this->frontSide->getText(); // deviation: default

            if (preg_match($patterns['text'], $textFront)) {
                return true;
            }

            if (!empty($textFront)) {
                $textFull .= $textFront;
            }
        }

        // BACK

        if ($this->backSide) {
            $backLogos = $this->backSide->getDOM(0)->findNodes('/img[@left > 50]/@alt', null, $patterns['image']);
            $backLogoValues = array_values(array_filter($backLogos));

            if (!empty($backLogoValues[0])) {
                return true;
            }

            $textBack = $this->backSide->getText(); // deviation: default

            if (preg_match($patterns['text'], $textBack)) {
                return true;
            }

            if (!empty($textBack)) {
                $textFull .= "\n" . $textBack;
            }
        }

        // FULL

        /**
         * @CardExample(accountId=4152222, cardUuid="383c4155-7434-45a7-ad9c-64d810b21ef1", groupId="formatCC1")
         */
        $textFrontRightTop = $this->frontSide ? $this->frontSide->getDOM(1)->getTextRectangle(60, 0, 0, 60) : ''; // deviation: 0-3
        $textBackRightTop = $this->backSide ? $this->backSide->getDOM(1)->getTextRectangle(60, 0, 0, 60) : ''; // deviation: 0-3

        $textFullRightTop = $textFrontRightTop . "\n" . $textBackRightTop;
        $condition1 = preg_match('/^Visa/m', $textFullRightTop) > 0;
        $condition2 = preg_match('/(?:\b|\D)\d{4} \d{4} \d{4} \d{4}(?:\b|\D)/', $textFull) > 0;

        if ($condition1 && $condition2) {
            return true;
        }

        return false;
    }

    protected function hideCCNumber_1(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(0, 50, 65, 50)])
            ->setBack($rects);
    }
}
