<?php

namespace AwardWallet\Engine\westjet\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserWestjet implements CardImageParserInterface, CreditCardDetectorInterface
{
    /**
     * @Detector(version="1")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        // example accounts: 4332392,4070791,4294801,2375908,4161000

        $ccDetectionResult = new CreditCardDetectionResult();

        $frontSide = $cardRecognitionResult->getFront();
        $backSide = $cardRecognitionResult->getBack();

        $patterns = [
            'image' => '/^(?:Royal Bank)/i',
            'text'  => '/(?:MasterCard|\bRoyal Bank\b)/i',
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
         * @CardExample(accountId=4068899, cardUuid="8a43879a-5d0e-4115-8f26-6b2d7337b270", groupId="format1BadSimbols")
         */
        $textFrontConverted = str_replace(['S'], ['5'], $textFront);

        $textFrontNoSpaces = str_replace(' ', '', $textFrontConverted);

        /**
         * @CardExample(accountId=3829001, cardUuid="9fed7b3a-86a6-4cb1-9880-a9c3929ef00b", groupId="format1")
         * @CardExample(accountId=4963263, cardUuid="0250a6f5-be71-4ace-988f-193f2566521d", groupId="format1")
         */
        $pattern = '/'
            . '(?:\b|\D)(?<number>\d{9})(?:\b|\D)' // Number
            . '/';

        if (preg_match($pattern, $textFrontConverted, $matches)
            || preg_match($pattern, $textFrontNoSpaces, $matches)
        ) {
            $result['Login'] = $matches['number'];
        }

        return $result;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // WestJet ID
        ];
    }

    private function hideCCNumber(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(5, 30, 90, 50)])
            ->setBack($rects);
    }
}
