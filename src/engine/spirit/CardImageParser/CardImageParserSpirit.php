<?php

namespace AwardWallet\Engine\spirit\CardImageParser;

use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserSpirit implements CardImageParserInterface, CreditCardDetectorInterface
{
    private $devMode = 0;

    private $frontSide;
    private $backSide;

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        return $properties;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Email or FREE SPIRIT Number
        ];
    }

    /**
     * @Detector(version="1")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        // example accounts: 930897,2288868,2683004,3722525,3895364,3900425,3932149,3983549,3986075,3990422,3990425

        $ccDetectionResult = new CreditCardDetectionResult();

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        if (!$this->frontSide && !$this->backSide) {
            return $ccDetectionResult;
        }

        $patterns = [
            'image' => '/(?:MasterCard)/i',
            'text'  => '/(?:\bMasterCard?|ban[kh]\s*o[fl]\s*am[ea]rica)/i',
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

        if ($this->frontSide && $this->backSide && preg_match('/spirit\s+\d{1,2}\/\d{2}/i', $this->frontSide->getText()) && preg_match('/\d{3}\s+cirrus/i', $this->backSide->getText())) {
            $this->hideCCNumber($ccDetectionResult);
        }

        return $ccDetectionResult;
    }

    private function parseFormat_1()
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        return $properties;
    }

    private function hideCCNumber(CreditCardDetectionResult &$ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(5, 30, 90, 50)])
            ->setBack($rects);
    }
}
