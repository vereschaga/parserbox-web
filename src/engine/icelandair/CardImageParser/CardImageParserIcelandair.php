<?php

namespace AwardWallet\Engine\icelandair\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserIcelandair implements CardImageParserInterface, CreditCardDetectorInterface
{
    /**
     * @Detector(version="1")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        // example accounts: not examples, but bank cards are exists
        $ccDetectionResult = new CreditCardDetectionResult();

        $frontSide = $cardRecognitionResult->getFront();
        $backSide = $cardRecognitionResult->getBack();

        $patterns = [
            'image' => '/^(?:MasterCard)/i',
            'text'  => '/(?:MasterCard|\bFirst Bankcard\b)/i',
        ];

        // FRONT

        if ($frontSide) {
            $frontLogos = $frontSide->getDOM()->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
            $frontLogoValues = array_values(array_filter($frontLogos));

            if (!empty($frontLogoValues[0])) {
                $this->hideCCNumber($ccDetectionResult);
            }

            $textFront = $frontSide->getText();

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

            $textBack = $backSide->getText();

            if (preg_match($patterns['text'], $textBack)) {
                $this->hideCCNumber($ccDetectionResult);
            }
        }

        return $ccDetectionResult;
    }

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $result = [];

        $frontSide = $cardRecognitionResult->getFront();

        $textFront = $frontSide ? $frontSide->getText() : '';

        /**
         * @CardExample(accountId=4920734, cardUuid="e25cd9e7-aee1-4954-bbdf-1e9ecb60be68", groupId="format1")
         * @CardExample(accountId=3907984, cardUuid="067b75e3-9e56-47d6-b3f8-6d436d159791", groupId="format1")
         */
//        $textFrontConverted = str_replace(['S'], ['5'], $textFront);

        $pattern = '/'
            . '\n\s*(?<number>\d{6} ?\d{4})\s*(?:\n|$)' // Number
            . '/';

        if (preg_match($pattern, $textFront, $matches)) {
            $result['Login'] = str_replace(' ', '', $matches['number']);
        }

        /**
         * @CardExample(accountId=4445042, cardUuid="44026876-72d2-4582-9dc4-55a6084020b7", groupId="format2")
         */
        $textFrontConverted = str_replace(['S', 'b', 'l'], ['5', '6', '1'], $textFront);
        $pattern = '/'
            . '\n\s*(?<number>\d{6} ?\d{4} ?\d{2})\s*(?:\n|$)' // Number
            . '/';

        if (preg_match($pattern, $textFrontConverted, $matches)) {
            $result['Login'] = str_replace(' ', '', $matches['number']);
        }

        return $result;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Saga Club Number
        ];
    }

    private function hideCCNumber(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(5, 30, 90, 50)])
            ->setBack($rects);
    }
}
