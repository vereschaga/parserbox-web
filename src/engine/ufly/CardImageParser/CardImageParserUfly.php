<?php

namespace AwardWallet\Engine\ufly\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserUfly implements CardImageParserInterface, CreditCardDetectorInterface
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
            'image' => '/Visa/i',
            'text'  => '/(?:irstbankcard\.com|First National Bank|VISA)/',
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
        $textBack = $backSide ? $backSide->getText() : '';

        $convertFrom = ['O'];
        $convertTo = ['0'];

        $textFrontNoSpaces = str_replace($convertFrom, $convertTo, str_replace(' ', '', $textFront));
        $textFull = $textFront . "\n" . $textBack;
        $textFullNoSpaces = str_replace($convertFrom, $convertTo, str_replace(' ', '', $textFull));

        /**
         * @CardExample(accountId=5263699, cardUuid="8a36b37b-89cd-43e3-9b57-d77ee39da8fb", groupId="format1")
         */
        $pattern = '/(?:^|\s+)'
            . '(?<number>\d{9})' // Number
            . '(?:\s+|$)/';

        if (preg_match($pattern, $textFront, $matches)
                || preg_match($pattern, $textFrontNoSpaces, $matches)
                || preg_match($pattern, $textFullNoSpaces, $matches)
        ) {
            $result['Login'] = $matches['number'];
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
