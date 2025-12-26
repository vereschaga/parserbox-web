<?php

namespace AwardWallet\Engine\japanair\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserJapanair implements CardImageParserInterface, CreditCardDetectorInterface
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
        }

        return $this->ccDetectionResult;
    }

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        if (!$this->frontSide) {
            $this->frontSide = $cardRecognitionResult->getFront();
        }

        if (!$this->backSide) {
            $this->backSide = $cardRecognitionResult->getBack();
        }

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

        // Number (Type 1)
        /**
         * @CardExample(accountId=3911806, cardUuid="06cc3b2f-086b-4461-a383-5ba7f08e1160")
         */
        $textFront = $this->frontSide ? $this->frontSide->getText(5) : ''; // deviation: 2-9

        if (preg_match('/\b(\d{9})\b/', $textFront, $matches)) {
            $properties['Login'] = $matches[1];

            return $properties;
        }

        // Number (Type 2)
        /**
         * @CardExample(accountId=3653780, cardUuid="ed48221f-7c4f-4e71-8047-28499dd31f53")
         */
        $textFront = $this->frontSide ? $this->frontSide->getText() : ''; // deviation: default

        $in = ['.', 'O', 'o', 'U', 'l', 'b', '#'];
        $out = ['', '0', '0', '0', '1', '6', '4'];
        $textFrontConverted = str_replace($in, $out, $textFront);

        if (preg_match('/\b([\d ]{9,})\b/', $textFrontConverted, $matches)) {
            $matches[1] = preg_replace("#\s+#", '', $matches[1]);

            if (strlen($matches[1]) == 9) {
                $properties['Login'] = $matches[1];

                return $properties;
            }
        }

        return $properties;
    }

    protected function detectCC_1(): bool
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        /**
         * @CardExample(accountId=3005801, cardUuid="63d57c02-e089-49cc-a3da-0eca4ebdf12c")
         */
        $frontSide = $this->frontSide ? $this->frontSide->getText() : ''; // deviation: default

        if (stripos($frontSide, 'AMERICAN') !== false && stripos($frontSide, 'EXPRESS') !== false) {
            return true;
        }

        $textBack = $this->backSide ? $this->backSide->getText() : ''; // deviation: default

        if (stripos($textBack, 'MUFG CARD') !== false || stripos($textBack, 'Cardmember Signature') !== false
                || preg_match('#AUTHORIZED[ ]*SIGNATURE#ui', $textBack)) {
            return true;
        }

        return false;
    }

    protected function hideCCNumber_1(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront([new Rectangle(0, 40, 100, 50)])
            ->setBack([new Rectangle(0, 20, 100, 50)]);
    }
}
