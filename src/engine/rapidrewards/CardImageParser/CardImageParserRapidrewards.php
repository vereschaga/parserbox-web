<?php

namespace AwardWallet\Engine\rapidrewards\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserRapidrewards implements CardImageParserInterface, CreditCardDetectorInterface
{
    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $result = [];

        if ($frontSide = $cardRecognitionResult->getFront()) {
            $frontText = $frontSide->getText();

            /**
             * @CardExample(cardUuid="f52c8beb-044b-45ea-8a96-9b341244469f", accountId=136825)
             */
            if (preg_match("#RAPID REWARDS\s+\#?(?:00000)?(\d+)#i", $frontText, $m) && strlen($m[1]) >= 8 && strlen($m[1]) <= 11) {
                $result["Login"] = $m[1];

            /**
             * @CardExample(cardUuid="7b9ee407-0e07-468e-a8c1-bbaffc65912e", accountId=3791360)
             */
            } elseif (preg_match("#Rapid Rewards Account Number:\s+(\d+)#", $frontText, $m) && strlen($m[1]) >= 8 && strlen($m[1]) <= 11) {
                $result["Login"] = $m[1];

            /**
             * @CardExample(cardUuid="b013cc2d-0eb4-4164-af96-749209a874e1", accountId=3861405)
             */
            } elseif (preg_match("#\nRR (\d+)\n#", $frontText, $m) && strlen($m[1]) >= 8 && strlen($m[1]) <= 11) {
                $result["Login"] = $m[1];

            /**
             * @CardExample(cardUuid="74dba092-1a9d-456d-94c0-56fa9adb5fe9", accountId=1144270)
             */
            } elseif (preg_match("#\n0000 (\d{9} \d)\n#", $frontText, $m)) {
                $result["Login"] = str_replace(" ", "", $m[1]);

            /**
             * @CardExample(cardUuid="08af4fb1-a76d-40fb-8f2a-3fc7b361bd55", accountId=3961616)
             */
            } elseif (preg_match("#^0{4,5}(\d+)\n#", $frontText, $m)) {
                $result["Login"] = $m[1];

            /**
             * @CardExample(cardUuid="e09ec58c-0fa5-413a-8bdc-57d0581ea006", accountId=3864282)
             */
            } elseif (preg_match("#(?:^|\D|\n)(\d{8,11})(?:\D|$|\n)#", $frontText, $m)) {
                $result["Login"] = $m[1];
            }
        }

        return $result;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login',
        ];
    }

    /**
     * @Detector(version="1")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        $ccDetectionResult = new CreditCardDetectionResult();

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        if (!$this->frontSide && !$this->backSide) {
            return $ccDetectionResult;
        }

        if ($this->detectCC() == true) {
            $this->hideCCNumber($ccDetectionResult);
        }

        return $ccDetectionResult;
    }

    /**
     * @CardExample(accountId=3929490, cardUuid="cfe053ca-1d35-486f-a7b2-7c0990c2315e")
     * @CardExample(accountId=2397901, cardUuid="fd247126-7182-47fb-936a-8024a9089daa")
     */
    protected function detectCC(): bool
    {
        if ($this->frontSide) {
            $textFront = $this->frontSide->getText();

            if (
                       stripos($textFront, 'VISA') !== false
                    || stripos($textFront, 'MasterCard') !== false
                    || preg_match("#\n\d{4} \d{4} \d{4} \d{4}\n#", $textFront)
                    || preg_match("#\s*GOOD\s*\n\s*THRU\b#", $textFront)
                    ) {
                return true;
            }
        }

        if ($this->backSide) {
            $textBack = $this->backSide->getText();

            if (
                       stripos($textBack, 'VISA') !== false
                    || stripos($textBack, 'MasterCard') !== false
                    || stripos($textBack, 'without Authorized Signature') !== false
                    ) {
                return true;
            }
        }

        return false;
    }

    protected function hideCCNumber(CreditCardDetectionResult &$ccDetectionResult)
    {
        $rects = [new Rectangle(0, 30, 100, 60)];
        $ccDetectionResult
            ->setFront($rects)
            ->setBack($rects);
    }
}
