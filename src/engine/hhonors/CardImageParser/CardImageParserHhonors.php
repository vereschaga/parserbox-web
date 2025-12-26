<?php

namespace AwardWallet\Engine\hhonors\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;
use AwardWallet\CardImageParser\ImageRecognitionResult;
use AwardWallet\CardImageParser\RegexpUtils as R;

class CardImageParserHhonors implements CardImageParserInterface, CreditCardDetectorInterface
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
         * @CardExample(accountId=2009446, cardUuid="68a2de9c-aadf-43de-ab4d-ad3d111255df", groupId="format1")
         * @CardExample(accountId=3971069, cardUuid="8789208b-8cf7-42a9-be64-8c3687a5885e", groupId="format1")
         */
        if ($properties = $this->parseFormat_1()) {
            return $properties;
        }

        return $properties;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Honors # / Username
        ];
    }

    /**
     * @Detector(version="4")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        // example accounts: 2950795,3646995,4026308,3827696,2932817,3483489

        $ccDetectionResult = new CreditCardDetectionResult();

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        if (!$this->frontSide && !$this->backSide) {
            return $ccDetectionResult;
        }

        if ($this->detectCC_1()) {
            $this->hideCCNumber_1($ccDetectionResult);
        }

        return $ccDetectionResult;
    }

    protected function parseFormat_1()
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        $textFront = $this->frontSide ? $this->frontSide->getText() : ''; // deviation: default
        $textBack = $this->backSide ? $this->backSide->getText() : ''; // deviation: just as $textFront

        if (stripos($textBack, 'Mexico') !== false) {
            $textBack = '';
        }

        $textFull = $textFront . "\n" . $textBack;

        // Number

        $textFullConverted = str_replace('.', '', $textFull);

        /**
         * @CardExample(accountId=3972611, cardUuid="a6587d70-64b0-44b1-b159-35d3065a10a5", groupId="format1")
         */
        $textFullConverted = preg_replace('/\b\d{3}-\d{3}-\d{4}\b/', '', $textFullConverted); // remove CUSTOMER CARE phone number
        $textFullConverted = str_replace(' ', '', $textFullConverted);

        // Step 1. Hard Parsing
        if (preg_match('/(?:\b|\D)(?<number>\d{9,10})(?:\b|\D)/', $textFullConverted, $m)) {
            $properties['Login'] = $m['number'];
        }

        // Step 2. Soft Parsing
        if (empty($properties['Login'])
            && preg_match('/(?:\b|\D)(?<number>(?:' . R::digitsPattern() . '){9,10})(?:\b|\D)/', $textFullConverted, $m)
            && is_numeric($number = R::digitize($m['number']))
        ) {
            $properties['Login'] = $number;
        }

        return $properties;
    }

    protected function detectCC_1(): bool
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $textFront = $this->frontSide ? $this->frontSide->getText() : ''; // deviation: default
        $textBack = $this->backSide ? $this->backSide->getText() : ''; // deviation: default
        $textFull = $textFront . "\n" . $textBack;

        if (stripos($textFull, 'americanexpress.com') !== false
            || preg_match('/\b(?:MERICAN|AMERI|MERIC)\s+(?:EXPR|XPRES|EXPRES)\s*$/i', $textFull)
        ) {
            return true;
        }

        if (preg_match('/Valid\s+from\s+Good\s+thru/i', $textFull)) {
            return true;
        }

        if (strpos($textFront, "\nVISA\n") !== false
            || stripos($textBack, 'Not valid unless signed') !== false
            || stripos($textBack, 'Authorized signature') !== false
        ) {
            return true;
        }

        if (preg_match('/(?:ericanexpress\.com\/surpass|US 1-833-698-2567)/i', $textFull)
            || preg_match('/Member.+Since[\s\S]+(?:[O ](?-i)AMEX(?i)|AMERICAN?\s*EXPRES*)(?:\s|$)/i', $textFront)
        ) {
            return true;
        }

        if (!empty($this->frontSide)
            && !empty(array_filter($this->frontSide->getDOM()->findNodes('//img[(@top >= 50 or @top < 20) and @left > 70]/@alt', null, '/(?:^Visa$|American\s*Express|Hilton\s*Anchorage)/i')))
            && (stripos($textFront, 'Credit') !== false || stripos($textFront, 'Valid Thru') !== false)
        ) {
            return true;
        }

        if (preg_match('/\d{4}\s+\d{6}\s+\d{5}\s+\d{3}/', $textBack) && false !== stripos($textFront, 'Valid Thru')) {
            return true;
        }

        if (!empty($this->frontSide) && !empty($this->backSide)) {
            if (preg_match('/\d{4}\s+\d{6}\s+\d{5}\nAMERICAN\nEXPRESS/', $textBack)
                && !empty(array_filter($this->frontSide->getDOM()->findNodes('//img[(@top >= 70) and @left > 80]/@alt', null, '/(American\s+Express)/i')))
            ) {
                return true;
            }
        }

        return false;
    }

    protected function hideCCNumber_1(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront([new Rectangle(5, 30, 90, 40), new Rectangle(5, 70, 65, 20)])
            ->setBack([new Rectangle(5, 20, 90, 45), new Rectangle(60, 65, 30, 20)]);
    }
}
