<?php

namespace AwardWallet\Engine\golair\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserGolair implements CardImageParserInterface, CreditCardDetectorInterface
{
    protected $devMode = 0;

    protected $frontSide;
    protected $backSide;

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $result = [];

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        if (!$this->frontSide && !$this->backSide) {
            return [];
        }

        if ($result = $this->parseFormat_1()) {
            return $result;
        }

        return $result;
    }

    /**
     * @Detector(version="2")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        // example accounts: 4454114,4537829,4730584

        $ccDetectionResult = new CreditCardDetectionResult();

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        if (!$this->frontSide && !$this->backSide) {
            return $ccDetectionResult;
        }

        // FRONT

        if ($this->frontSide) {
            $domFront = $this->frontSide->getDOM(0);
            $textFront = $this->frontSide->getText();

            $frontLogos = $domFront->findNodes('/img/@alt', null, '/(express|paypal|^Visa$|mastercard)/i');
            $frontLogoValues = array_values(array_filter($frontLogos));

            if (
                !empty($frontLogoValues[0])
                || $textFront->findPreg('/(?:america|express|paypal|\bvisa\b|mastercard)/i') !== null
            ) {
                $this->hideCCNumber($ccDetectionResult);
            }
        }

        // BACK

        if ($this->backSide) {
            $textBack = $this->backSide->getText();

            if ($textBack->findPreg('/(?:americanexpress|american\s+express|\bvisa\b)/i') !== null) {
                $this->hideCCNumber($ccDetectionResult);
            }
        }

        return $ccDetectionResult;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Smiles number
        ];
    }

    protected function parseFormat_1()
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $result = [];

        /**
         * @CardExample(accountId=1317883, cardUuid="e0a12cef-354a-4584-8dba-7d33ef3c6031")
         * @CardExample(accountId=2040411, cardUuid="efdc3957-6c8e-4a70-a344-d270de63bfcf")
         * @CardExample(accountId=1900759, cardUuid="4c7c6078-39ec-4ebd-8b44-7a5d8375517c")
         * @CardExample(accountId=3752537, cardUuid="f7f459fa-502b-49c0-98f3-7df9bbaf5885")
         * @CardExample(accountId=3863202, cardUuid="82c31aa6-53b6-4b5f-a5bc-ee6df90d29b3")
         * @CardExample(accountId=3584706, cardUuid="5712f876-8be7-4da7-a895-a03d67028254")
         * @CardExample(accountId=3909212, cardUuid="79d6fb12-668c-453d-8335-136cc0b67ba6")
         * @CardExample(accountId=3858798, cardUuid="2e91b490-ad00-466c-8a09-30c7ac1a527e")
         * @CardExample(accountId=3792820, cardUuid="075153b5-2e73-48c8-99e2-4067d57025bf")
         * @CardExample(accountId=3872379, cardUuid="34b342d6-b598-4c3d-bb4b-ad57691e0058")
         */
        $textFront = $this->getTextRectangle('front', 3, 0, 0, 5, 0); // deviation: 1-6

        /**
         * @CardExample(accountId=3858779, cardUuid="430e67ce-617a-40ff-8292-9b2be2908b78")
         */
        $textBack = $this->getTextRectangle('back', 16, 0, 0, 50, 0); // deviation: 1-33

        $textFull = $textFront . "\n" . $textBack;

        // Number

        $textFrontConverted = str_replace('.', '', $textFull);

        if (preg_match('/\b(\d{9})\b/', $textFrontConverted, $matches)) {
            $result['Login'] = $matches[1];
        } elseif (preg_match('/\b(\d{3}[ ]*\d{3}[ ]*\d{3})\b/', $textFrontConverted, $matches)) {
            $result['Login'] = str_replace(' ', '', $matches[1]);
        }

        return $result;
    }

    protected function getTextRectangle($side = 'front', $deviation = 7, $marginLeft = 0, $marginRight = 0, $marginTop = 0, $marginBottom = 0)
    {
        if ($side === 'front' && $this->frontSide) {
            $dom = $this->frontSide->getDOM($deviation);
        } elseif ($side === 'back' && $this->backSide) {
            $dom = $this->backSide->getDOM($deviation);
        } else {
            return '';
        }

        $xpathFragment = '/span[@left >= ' . $marginLeft . ' and @left <= ' . (100 - $marginRight) . ' and @top >= ' . $marginTop . ' and @top <= ' . (100 - $marginBottom) . ']';

        $rowsText = [];

        $rowNumber = 1;
        $maxRows = 100;

        while ($rowNumber < $maxRows) {
            if ($dom->findSingleNode('/div[' . $rowNumber . ']') !== null) {
                $rowTexts = $dom->findNodes('/div[' . $rowNumber . ']' . $xpathFragment);
                $rowsText[] = implode('', $rowTexts);
            } else {
                break;
            }
            $rowNumber++;
        }

        return trim(implode("\n", $rowsText));
    }

    protected function hideCCNumber(CreditCardDetectionResult &$ccDetectionResult)
    {
        $rects = [new Rectangle(0, 30, 100, 60)];
        $ccDetectionResult
            ->setFront($rects)
            ->setBack($rects);
    }
}
