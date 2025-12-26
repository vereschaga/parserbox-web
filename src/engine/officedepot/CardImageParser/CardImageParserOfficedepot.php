<?php

namespace AwardWallet\Engine\officedepot\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserOfficedepot implements CardImageParserInterface, CreditCardDetectorInterface
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
            //            'image' => '//i',
            //            'text' => '//i',
        ];

        // FRONT

        if ($frontSide) {
//            $frontLogos = $frontSide->getDOM()->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
//            $frontLogoValues = array_values( array_filter($frontLogos) );
//            if ( !empty($frontLogoValues[0]) ) {
//                $this->hideCCNumber($ccDetectionResult);
//            }
//
//            $textFront = $frontSide->getText();
//            if ( preg_match($patterns['text'], $textFront) ) {
//                $this->hideCCNumber($ccDetectionResult);
//            }
        }

        // BACK

        if ($backSide) {
//            $backLogos = $backSide->getDOM()->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
//            $backLogoValues = array_values( array_filter($backLogos) );
//            if ( !empty($backLogoValues[0]) ) {
//                $this->hideCCNumber($ccDetectionResult);
//            }
//
//            $textBack = $backSide->getText();
//            if ( preg_match($patterns['text'], $textBack) ) {
//                $this->hideCCNumber($ccDetectionResult);
//            }
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

        /**
         * @CardExample(accountId=4742198, cardUuid="48a67515-8397-4ad4-ba69-9d4591efc0ce", groupId="format1")
         * @CardExample(accountId=4746202, cardUuid="a1b34328-4c15-4f84-b42f-b7541c092d9a", groupId="format1")
         */
        $pattern = '/\bMember *(?:ID *)?[^\w\s]? *(?<number>\d(?: ?\d){8,9})\s*$/mi';

        if (preg_match($pattern, $textFront . "\n" . $textBack, $matches)
            || preg_match('/MaxPerksID *[^\w\s]? *(?<number>\d[\d \-]{7,})\s*$/mi', $textFront . "\n" . $textBack, $matches)
            || preg_match('/^\s*(?<number>\d{10})\s*$/mi', $textFront . "\n" . $textBack, $matches)
        ) {
            $result['Login'] = str_replace(['-', ' '], '', $matches['number']);
        }

        if (
            !preg_match('/\bMember\b/i', $textFront . "\n" . $textBack, $matches)
            && preg_match('/^\s*(?<number>\d{10})\s*$/mi', $textFront . "\n" . $textBack, $matches)
        ) {
            /** @CardExample(accountId=4455834, cardUuid="2219845f-69ea-4c8c-92c9-3487d091b7ed", groupId="format1") */
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
            ->setFront($rects = [new Rectangle(5, 30, 90, 50)])
            ->setBack($rects);
    }
}
