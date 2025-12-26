<?php

namespace AwardWallet\Engine\lanpass\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;
use AwardWallet\CardImageParser\ImageRecognitionResult;

class CardImageParserLanpass implements CardImageParserInterface, CreditCardDetectorInterface
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

        /**
         * @CardExample(accountId=3966820, cardUuid="54e2792b-38bc-48df-81e4-c0f9e77898e6", groupId="format1")
         * @CardExample(accountId=3966865, cardUuid="a9eb91a1-9992-42e0-b98a-b367f5ffb069", groupId="format1")
         * @CardExample(accountId=3914943, cardUuid="6c87fde2-f6c9-45f8-ad71-ac840485d5fb", groupId="format1")
         * @CardExample(accountId=3858829, cardUuid="a1714180-8bde-46be-86d7-996114c68131", groupId="format1")
         * @CardExample(accountId=2697598, cardUuid="d40e70c3-6868-4714-90a2-930a85e99b4d", groupId="format1")
         * @CardExample(accountId=3835519, cardUuid="19f73ed4-2af5-4e04-ad2c-0c66c7f9b0ca", groupId="format1")
         * @CardExample(accountId=3778052, cardUuid="bf1d1850-6f58-4319-8fd5-73e179475387", groupId="format1")
         * @CardExample(accountId=3919084, cardUuid="a2bf3f0e-03f8-4d11-9a36-eb91d171429f", groupId="format1")
         * @CardExample(accountId=3837287, cardUuid="cbc1b4fe-237c-4faf-90fc-5ee2c6113a08", groupId="format1")
         * @CardExample(accountId=4236295, cardUuid="1aa11e72-3428-4b20-a900-95eb0f991e9d", groupId="format1")
         */
        if ($properties = $this->parseFormat_1()) {
            return $properties;
        }

        return $properties;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Username / Membership #
        ];
    }

    /**
     * @Detector(version="1")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        // example accounts: 4282431,4296967,3845439

        $ccDetectionResult = new CreditCardDetectionResult();

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        if (!$this->frontSide && !$this->backSide) {
            return $ccDetectionResult;
        }

        $patterns = [
            'image' => '/(?:Visa|BBVA Banco Franc[ée]|Banco del Pichincha|MasterCard)/iu',
            'text'  => '/(?:\bVISA\b|BVA Franc[ée]|\bPICHINCHA\b|MasterCard)/iu',
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

        // Number

        // FRONT

        $textFront = $this->frontSide ? $this->frontSide->getText() : ''; // deviation: default

        if ($number = $this->parseNumber($textFront)) {
            $properties['Login'] = $number;

            return $properties;
        }

        // BACK

        /**
         * @CardExample(accountId=3993632, cardUuid="59004a92-0761-4f0c-857e-dc303a47daea", groupId="format1Back")
         */
        $textBack = $this->backSide ? $this->backSide->getText() : ''; // deviation: default

        if ($number = $this->parseNumber($textBack)) {
            $properties['Login'] = $number;
        }

        return $properties;
    }

    protected function parseNumber($text): string
    {
        $textConverted = str_replace('.', '', $text);

        $textConverted = preg_replace('/\b\d{1,3}[ ]*\/[ ]*\d{1,4}/', '', $textConverted); // remove: 03/2018  |   041/20 in 4048803

        preg_match_all('/(?:\D|^)(\d[\d ]{7,}\d)[ ]*(?:\D|$)/m', $textConverted, $numberMatches);

        foreach ($numberMatches[1] as $number) {
            $number = str_replace(' ', '', $number);

            if (preg_match('/^\d{12}$/', $number)) {
                return $number;
            } elseif (preg_match('/^\d{11}$/', $number)) {
                return $number;
            } elseif (preg_match('/^\d{10}$/', $number)) {
                return $number;
            } elseif (preg_match('/^\d{9}$/', $number)) {
                return $number;
            } elseif (preg_match('/^\d{16}$/', $number)) {
                return $number;
            }
        }

        return '';
    }

    private function hideCCNumber(CreditCardDetectionResult &$ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(5, 30, 90, 40), new Rectangle(30, 70, 40, 25)])
            ->setBack($rects);
    }
}
