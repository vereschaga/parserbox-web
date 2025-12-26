<?php

namespace AwardWallet\Engine\mileageplus\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserMileageplus implements CardImageParserInterface, CreditCardDetectorInterface
{
    protected $devMode = 0;

    protected $ccDetectionResult;

    protected $frontSide;
    protected $backSide;

    protected $statusVariants = ['Global[ ]*Services', 'GLOBAL[ ]*SERVICES', 'Platinum', 'PLATINUM', 'Silver', 'SILVER', 'Gold', 'GOLD', 'Member', 'MEMBER', '1K'];

    // https://www.united.com/web/en-US/apps/account/settings/accountNumberResolution.aspx
//    protected $nameTitles = ['Mr.', 'Mrs.', 'Ms.', 'Dr.', 'Mstr.', 'Miss', 'Prof.', 'Rev.', 'Sir', 'Sister'];

    protected $patterns = [
        'Number (11 digits)'           => '[\d]{11}', // 10208 945 350
        'Number (8 chars)'             => '([A-Zj]{1,2}[A-Zj\d])([\dO]\d{4})', // EJM90697    |    EjM90697    |    E790697
        'Number (11 digits) - replace' => '[b\d]{11}', // 00137 b61 047
    ];

    /**
     * @Detector(version="1")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        $this->ccDetectionResult = new CreditCardDetectionResult();

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        if (!$this->frontSide && !$this->backSide) {
            return $this->ccDetectionResult;
        }

        if ($this->detectCC_1()) {
            $this->hideCCNumber_1($this->ccDetectionResult);

            return $this->ccDetectionResult;
        }

        return $this->ccDetectionResult;
    }

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        if (!$this->frontSide && !$this->backSide) {
            return [];
        }

        /**
         * @CardExample(accountId=4056272, cardUuid="af0f489b-1864-47f5-a407-dce0232e66c1", groupId="format1")
         */
        $textFront = $this->frontSide ? $this->frontSide->getText(3) : ''; // deviation: 0-6
        $textBack = $this->backSide ? $this->backSide->getText(3) : ''; // deviation: 0-6

        if (preg_match('/NAME[ ]+ACCOUNT[ ]+NUMBER/i', $textFront . "\n" . $textBack)) { // NAME    ACCOUNT NUMBER
            if ($properties = $this->parseFormat_1()) {
                return $properties;
            }
        }

        // Other Formats
        if ($properties = $this->parseFormat_999()) {
            return $properties;
        }

        return $properties;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Number
            'Status', // Elite Status
        ];
    }

    protected function parseFormat_1()
    {
        // example accounts: 4056272,4137455

        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        // Number

        $textFront = $this->frontSide ? $this->frontSide->getText(4) : ''; // deviation: 1-6
        $textBack = $this->backSide ? $this->backSide->getText(4) : ''; // deviation: 1-6

        $textFull = $textFront . "\n" . $textBack;

        $textFullConverted = str_replace(['.', ':'], '', $textFull);

        if (preg_match('/^[A-z][-\'A-z ]*\b[ ]+' . $this->patterns['Number (8 chars)'] . '$/m', $textFullConverted, $matches)) {
            $properties['Login'] = strtoupper($matches[1]) . str_replace(['O'], ['0'], $matches[2]);
        }

        // Elite Status (FRONT)

        $textFrontRightTop = $this->frontSide ? $this->frontSide->getDOM(3)->getTextRectangle(50, 0, 0, 50) : ''; // deviation: 1-6

        if (preg_match('/\b(' . implode('|', $this->statusVariants) . ')\b/', $textFrontRightTop, $matches)) {
            $properties['Status'] = $matches[1];
        }

        // Elite Status (BACK)

        if (empty($properties['Status'])) {
            $textBackRightTop = $this->backSide ? $this->backSide->getDOM(3)->getTextRectangle(50, 0, 0, 50) : ''; // deviation: 1-6

            if (preg_match('/\b(' . implode('|', $this->statusVariants) . ')\b/', $textBackRightTop, $matches)) {
                $properties['Status'] = $matches[1];
            }
        }

        return $properties;
    }

    protected function parseFormat_999()
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        /**
         * @CardExample(accountId=3869600, cardUuid="e5d49d82-db3f-4a18-b401-d18f4edc2309", groupId="format999")
         * @CardExample(accountId=3895710, cardUuid="6d4a47db-55b0-4bc9-b8b2-13664d5948cc", groupId="format999")
         * @CardExample(accountId=3740871, cardUuid="ebdd1c67-2ed5-47ee-85ac-3ae1d669e345", groupId="format999")
         * @CardExample(accountId=4141393, cardUuid="89cf0c16-abaf-47c2-bf97-77cc5f46a0ad", groupId="format999")
         * @CardExample(accountId=3816840, cardUuid="97fa1987-33a7-41b5-a87b-b6c33601c769", groupId="format999")
         */

        // Number

        $textFront = $this->frontSide ? $this->frontSide->getDOM(3)->getTextRectangle(0, 0, 60, 0) : ''; // deviation: 1-6

        $textFrontConverted = $this->normalizeText($textFront);

        if (count(array_filter(array_map("trim", explode("\n", $textFrontConverted)))) === 1) {
            /**
             * @CardExample(accountId=1010693, cardUuid="d3b2fe55-026e-4b9f-8f4e-05fd4f8ab81f", groupId="format999")
             */
            $textFront = $this->frontSide ? $this->frontSide->getText() : '';
            $textFrontConverted = $this->normalizeText($textFront);
        }

        if (preg_match('/^[ ]*(' . $this->patterns['Number (11 digits)'] . ')(?:\D|\b)/m', $textFrontConverted, $matches)) {
            $properties['Login'] = $matches[1];
        } elseif (preg_match('/^[ ]*' . $this->patterns['Number (8 chars)'] . '/m', $textFrontConverted, $matches)) {
            /**
             * @CardExample(accountId=714920, cardUuid="a529a02b-c215-43a8-ac63-0e1a8aeb1706", groupId="format999")
             */
            $properties['Login'] = strtoupper($matches[1]) . str_replace(['O'], ['0'], $matches[2]);
        } elseif (preg_match('/^[ ]*(?<number>' . $this->patterns['Number (11 digits) - replace'] . ')[ ]*$/m', $textFrontConverted, $matches)) {
            /**
             * @CardExample(accountId=1385586, cardUuid="ce8cb0bb-ff7e-4c84-9c2a-d76231e96686", groupId="format999")
             */
            $properties['Login'] = str_replace(['b'], ['6'], $matches['number']);
        }

        // Elite Status (FRONT)

        /**
         * @CardExample(accountId=10185, cardUuid="8e5563b0-a3ff-48c2-bf37-b1b169f082fd", groupId="format999")
         */
        $textFront = $this->frontSide ? $this->frontSide->getDOM(4)->getTextRectangle(0, 0, 30, 25) : ''; // deviation: 1-9

        $textFrontConverted = $this->normalizeText($textFront);

        if (preg_match('/(' . implode('|', $this->statusVariants) . ')/', $textFrontConverted, $matches)) {
            $properties['Status'] = $matches[1];
        }

        if (empty($properties['Status'])) {
            /**
             * @CardExample(accountId=3712056, cardUuid="07d6c7a8-d5a2-4a4c-8812-0b5bd5dc88b5", groupId="format999")
             */
            $textFront = $this->frontSide ? $this->frontSide->getDOM(6)->getTextRectangle(0, 0, 0, 60) : ''; // deviation: 4-8

            $textFrontConverted = $this->normalizeText($textFront);

            if (preg_match('/PREMIER.*(' . implode('|', $this->statusVariants) . ').*MEMBER/s', $textFrontConverted, $matches)) {
                $properties['Status'] = $matches[1];
            }
        }

        // Elite Status (BACK)

        if (empty($properties['Status'])) {
            /**
             * @CardExample(accountId=1883739, cardUuid="45709f0c-9f2a-47ba-8269-5407c4a0545e", groupId="format999")
             */
            $textBack = $this->backSide ? $this->backSide->getDOM(6)->getTextRectangle(0, 0, 0, 80) : ''; // deviation: 1-29 (Warning: black line)

            $textBackConverted = $this->normalizeText($textBack);

            if (preg_match('/(' . implode('|', $this->statusVariants) . ')/', $textBackConverted, $matches)) {
                $properties['Status'] = $matches[1];
            }
        }

        return $properties;
    }

    protected function normalizeText($string = '')
    {
        $string = str_replace(['®', '.', ':', ' '], '', $string);

        return $string;
    }

    protected function detectCC_1(): bool
    {
        // example accounts: 2816444,2557441,3929493,1669378,613786,4049251

        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $patterns = [
            'image' => '/(?:VISA|Chase)/i',
            'text'  => '/(?:\bVISA\b|\bChase\b|Cardmember\s*Sign)/i',
        ];

        // FRONT

        if ($this->frontSide) {
            $frontLogos = $this->frontSide->getDOM(0)->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
            $frontLogoValues = array_values(array_filter($frontLogos));

            if (!empty($frontLogoValues[0])) {
                return true;
            }

            $textFront = $this->frontSide->getText(); // deviation: default

            if (preg_match($patterns['text'], $textFront)) {
                return true;
            }
        }

        // BACK

        if ($this->backSide) {
            $backLogos = $this->backSide->getDOM(0)->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
            $backLogoValues = array_values(array_filter($backLogos));

            if (!empty($backLogoValues[0])) {
                return true;
            }

            $textBack = $this->backSide->getText(); // deviation: default

            if (preg_match($patterns['text'], $textBack)) {
                return true;
            }
        }

        return false;
    }

    protected function hideCCNumber_1(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(0, 30, 100, 50)])
            ->setBack($rects);
    }
}
