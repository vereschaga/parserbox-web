<?php

namespace AwardWallet\Engine\goldcrown\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserGoldcrown implements CardImageParserInterface, CreditCardDetectorInterface
{
    private $devMode = 0;

    /**
     * @Detector(version="2")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        // example accounts: 3880027,3815369

        $ccDetectionResult = new CreditCardDetectionResult();

        $frontSide = $cardRecognitionResult->getFront();
        $backSide = $cardRecognitionResult->getBack();

        $patterns = [
            'image' => '/(?:Cirrus)/i',
            'text'  => '/(?:MasterCard|\bVisa\b|www[ ]*\.[ ]*mbna[ ]*\.[ ]*ca)/i',
        ];

        // FRONT

        if ($frontSide) {
            $frontLogos = $frontSide->getDOM()->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
            $frontLogoValues = array_values(array_filter($frontLogos));

            if (!empty($frontLogoValues[0])) {
                $this->hideCCNumber($ccDetectionResult);
            }

            $textFront = $frontSide->getText(); // deviation: default

            if (preg_match($patterns['text'], $textFront)) {
                $this->hideCCNumber($ccDetectionResult);
            }
        }

        // BACK

        if ($backSide) {
            $backLogos = $backSide->getDOM()->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
            $backLogoValues = array_values(array_filter($backLogos));

            if (!empty($backLogoValues[0])) {
                $this->hideCCNumber($ccDetectionResult);
            }

            $textBack = $backSide->getText(); // deviation: default

            if (preg_match($patterns['text'], $textBack)) {
                $this->hideCCNumber($ccDetectionResult);
            }
        }

        return $ccDetectionResult;
    }

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        $frontSide = $cardRecognitionResult->getFront();

        if (!$frontSide) {
            return [];
        }

        /**
         * @CardExample(accountId=1257179, cardUuid="dbf22531-9a78-4fc3-8424-18dc68a02bdc", groupId="format1")
         * @CardExample(accountId=2756382, cardUuid="93f48b7d-1ce6-4208-a96e-4d7af13944d4", groupId="format1")
         * @CardExample(accountId=3953193, cardUuid="e70d4e24-d6b2-4b85-a004-83be434c066d", groupId="format1")
         */
        $textFront = $frontSide->getText(4); // deviation: 1-6

        /**
         * @CardExample(accountId=2608247, cardUuid="fae5a4de-b4a9-48c7-af10-5d8861afcb7d", groupId="format1")
         */
        $textFrontConverted = str_replace(['.', '-'], '', $textFront);
        $textFrontConverted = str_replace("\n", ' ', $textFrontConverted);

        if ($result = $this->parseFormat_1($textFrontConverted)) {
            return $result;
        }

        return $properties;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Membership Number
        ];
    }

    private function parseFormat_1($textFrontConverted)
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        $pattern = '/'
            . '(?:^|[^\db])(?<number>[\db][\db ]{14,17}[\db])(?:$|[^\db])'	// Number
            . '/m';
        preg_match_all($pattern, $textFrontConverted, $numberMatches);

        foreach ($numberMatches['number'] as $number) {
            $number = str_replace([' ', 'b'], ['', '6'], $number);
            /**
             * @CardExample(accountId=2835296, cardUuid="33da1504-afa5-4a44-bc0d-5ab1fa49efc1", groupId="format1")
             */
            if (strlen($number) === 16) {
                $properties['Login'] = $number;

                break;
            }
        }

        return $properties;
    }

    private function hideCCNumber(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(0, 30, 100, 40)])
            ->setBack($rects);
    }
}
