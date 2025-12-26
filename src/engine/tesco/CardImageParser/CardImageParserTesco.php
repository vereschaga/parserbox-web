<?php

namespace AwardWallet\Engine\tesco\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserTesco implements CardImageParserInterface, CreditCardDetectorInterface
{
    private $devMode = 0;

    private $frontSide;
    private $backSide;

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        if (!$this->frontSide && !$this->backSide) {
            return [];
        }

        /**
         * @CardExample(accountId=1644796, cardUuid="1c6e84ab-62f6-494d-b018-a4b86e71f18a", groupId="format1")
         * @CardExample(accountId=1659096, cardUuid="ea769dd1-bcf0-45a0-acb6-6e42695c224c", groupId="format1")
         * @CardExample(accountId=3828448, cardUuid="0f252565-3c76-4a4b-b086-28a31e9021bd", groupId="format1")
         * @CardExample(accountId=2711971, cardUuid="3970a84c-0595-4723-bbcb-b489785e5f4a", groupId="format1")
         */
        if ($properties = $this->parseFormat_1()) {
            return $properties;
        }

        return $properties;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Tesco Clubcard #
        ];
    }

    /**
     * @Detector(version="2")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        // example accounts: 4242134,1649646

        $ccDetectionResult = new CreditCardDetectionResult();

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        if (!$this->frontSide && !$this->backSide) {
            return $ccDetectionResult;
        }

        $patterns = [
            'image' => '/(?:Visa|mastercard)/i',
            'text'  => '/(?:\bVISA\b|TESCO\s*Bank)/i',
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

    private function parseFormat_1()
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        $textFront = $this->frontSide ? $this->frontSide->getText() : ''; // deviation: default    |    4-5

        /**
         * @CardExample(accountId=3888538, cardUuid="a4ee2238-0e1a-4a52-86df-a7aed29dd9b6", groupId="format1")
         */
        $textBack = $this->backSide ? $this->backSide->getText(3) : ''; // deviation: 1-6

        $textFull = $textFront . "\n" . $textBack;

        // Number

        $textFrontConverted = str_replace(['.', ' '], '', $textFull);

        $badSimbols = ['O', '%', 'b'];

        preg_match_all('/(?:\b|\D)([\d' . implode('', $badSimbols) . ']{18})(?:\b|\D)/', $textFrontConverted, $numberMatches);

        foreach ($numberMatches[1] as $number) {
            $number = str_replace($badSimbols, ['0', '4', '6'], $number);

            if (strlen($number) === 18) {
                $properties['Login'] = $number;

                break;
            }
        }

        return $properties;
    }

    private function hideCCNumber(CreditCardDetectionResult &$ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(5, 30, 90, 40), new Rectangle(30, 70, 40, 25)])
            ->setBack($rects);
    }
}
