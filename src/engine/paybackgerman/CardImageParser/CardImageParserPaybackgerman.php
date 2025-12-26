<?php

namespace AwardWallet\Engine\paybackgerman\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserPaybackgerman implements CardImageParserInterface, CreditCardDetectorInterface
{
    /**
     * @Detector(version="1")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        $ccDetectionResult = new CreditCardDetectionResult();

        $frontSide = $cardRecognitionResult->getFront();
        $backSide = $cardRecognitionResult->getBack();

        $patterns = [
            'image' => '/American Express|MasterCard/i',
            'text'  => '/(?:American ?Express |MasterCard|paypass|Bank Zachodni)/i',
        ];

        // FRONT

        if ($frontSide) {
            $frontLogos = $frontSide->getDOM()->findNodes('/img[@left > 50 or @top > 50]/@alt', null, $patterns['image']);
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
            $backLogos = $backSide->getDOM()->findNodes('/img[@left > 50 or @top > 50]/@alt', null, $patterns['image']);
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
        $backSide = $cardRecognitionResult->getBack();

        $textFront = $frontSide ? $frontSide->getText() : '';
        $textFront5 = $frontSide ? $frontSide->getText(5) : '';
        $textBack = $backSide ? $backSide->getText() : '';
        $textBack5 = $backSide ? $backSide->getText(5) : '';

        /**
         * www.payback.de, payback.mx, PAYBACK.it.
         *
         * @CardExample(accountId=4410212, cardUuid="7a626dc5-835d-4b48-903e-b9d0491b6c10", groupId="germany")
         */
        $pattern = '/(?:^|\n)(?:.*[^\d\s])?\s*'
            . '(?<number>\d{3} ?\d{3} ?\d{4})' // Number
            . '\n/iu';

        if (preg_match($pattern, $textBack, $matches) || preg_match($pattern, $textBack5, $matches)
                || preg_match($pattern, $textFront, $matches) || preg_match($pattern, $textFront5, $matches)
        ) {
            $result['Login'] = str_replace(' ', '', $matches['number']);
        }

        /**
         * www.payback.id.
         *
         * @CardExample(accountId=4401912, cardUuid="8c999668-79c8-4364-8850-52f05d5c92c3", groupId="india")
         */
        $pattern = '/\s+'
            . '(?<number>940(?: ?\d){13})' // Number
            . '(?:\n|$)/iu';

        if (preg_match($pattern, $textBack, $matches) || preg_match($pattern, $textBack5, $matches)
                || preg_match($pattern, $textFront, $matches) || preg_match($pattern, $textFront5, $matches)
        ) {
            $result['Login'] = str_replace(' ', '', $matches['number']);
        }

        /**
         * www.payback.at.
         *
         * @CardExample(accountId=4484837, cardUuid="5bfdcb05-cfdc-47fa-9542-67a10b9b06a4", groupId="austria")
         */
        $pattern = '/\s+'
            . '(?<number>904(?: ?\d){13})' // Number
            . '(?:\n|$)/iu';

        if (preg_match($pattern, $textBack, $matches) || preg_match($pattern, $textBack5, $matches)
                || preg_match($pattern, $textFront, $matches) || preg_match($pattern, $textFront5, $matches)
        ) {
            $result['Login'] = str_replace(' ', '', $matches['number']);
        }

        return $result;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login',
        ];
    }

    private function hideCCNumber(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(5, 30, 90, 55)])
            ->setBack($rects);
    }
}
