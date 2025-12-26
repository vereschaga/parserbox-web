<?php

namespace AwardWallet\Engine\goldpassport\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserGoldpassport implements CardImageParserInterface, CreditCardDetectorInterface
{
    private $devMode = 0;

    private $namePrefixes = ['Miss', 'Mr.', 'Mrs.', 'Ms.', 'Dr.']; // https://world.hyatt.com/content/gp/en/enroll.html

    /**
     * @Detector(version="5")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        $ccDetectionResult = new CreditCardDetectionResult();

        $frontSide = $cardRecognitionResult->getFront();
        $backSide = $cardRecognitionResult->getBack();

        /**
         * @CardExample(accountId=3625348, cardUuid="263d9e24-8b6f-4e73-a4a2-67af58afad1c", groupId="format1cc")
         * @CardExample(accountId=3985227, cardUuid="be3c7f79-d56b-4127-abf1-8115e5631e33", groupId="format1cc")
         * @CardExample(accountId=3835882, cardUuid="ea860299-8531-4098-a9eb-d8eb0571c600", groupId="format1cc")
         */
        $patterns = [
            'image' => '/(?:Visa)/i',
            'text'  => '/(?:Valid\s+without|\bVISA\b)/i', // Not Valid without Authorized Signatur    |    VISA
        ];

        // FRONT

        if ($frontSide) {
            $frontLogos = $frontSide->getDOM(0)->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
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
            $backLogos = $backSide->getDOM(0)->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
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
        $properties = [];

        $frontSide = $cardRecognitionResult->getFront();
        $backSide = $cardRecognitionResult->getBack();

        /**
         * @CardExample(accountId=2315374, cardUuid="c1ce2b31-5c7d-4d94-9beb-0f187b09b296", groupId="format2")
         * @CardExample(accountId=2817199, cardUuid="15b2b3c6-3f89-4a8b-8a23-90a45558c912", groupId="format2")
         * @CardExample(accountId=4127675, cardUuid="6a4fafeb-84f4-4dbb-b7aa-efc5a2ff987f", groupId="format2")
         * @CardExample(accountId=4258237, cardUuid="cdb377f9-f8a1-4180-824c-b7ecd4e6a875", groupId="format2")
         * @CardExample(accountId=4291019, cardUuid="faa9ceb6-e76e-4a1d-a8fe-872c97893eaf", groupId="format2")
         */
        $textFront = $frontSide ? $frontSide->getText(4) : ''; // deviation: 0-6
        $textBack = $backSide ? $backSide->getText(4) : ''; // deviation: 0-6
        $condition1 = preg_match('/NAME[ ]+ACCOUNT[ ]+BALANCE/i', $textFront . "\n" . $textBack); // NAME    ACCOUNT BALANCE
        $textFrontTopRight = $frontSide ? $frontSide->getDOM(1)->getTextRectangle(55, 0, 0, 60) : ''; // deviation: 0-3
        $textBackTopRight = $backSide ? $backSide->getDOM(1)->getTextRectangle(55, 0, 0, 60) : ''; // deviation: 0-3
        $textFullTopRight = $textFrontTopRight . "\n" . $textBackTopRight;
        $textFullTopRightConverted = str_replace(['.', ':'], '', $textFullTopRight);
        $condition2 = preg_match('/(?:WORLD[ ]*OF[ ]*HYATT[ ]*#|EXPLORIST|\bMEMBER)$/im', $textFullTopRightConverted); // WORLD OF HYATT #    |    EXPLORIST    |    MEMBER

        if ($condition1 || $condition2) {
            if ($properties = $this->parseFormat_2($frontSide, $backSide, $textFullTopRightConverted)) {
                return $properties;
            }
        }

        /**
         * @CardExample(accountId=896844, cardUuid="91a7c2e8-4671-422b-b1ba-f66c801ac078", groupId="format3")
         * @CardExample(accountId=2616853, cardUuid="db03c473-317d-4b39-a836-21807fedc4cb", groupId="format3")
         * @CardExample(accountId=2348460, cardUuid="a5cce88c-09a6-41d9-92ec-75bd6d5e2c25", groupId="format3")
         * @CardExample(accountId=1171410, cardUuid="e8dd898a-9a4e-4050-be75-e7fb3f55e44a", groupId="format3")
         * @CardExample(accountId=3902772, cardUuid="2aa4d3ff-7798-4eb3-837d-ff212717df60", groupId="format3")
         * @CardExample(accountId=4289351, cardUuid="e41b4703-b94b-4743-93fa-7b402638d0b1", groupId="format3")
         */
        $textFront = $frontSide ? $frontSide->getDOM(5)->getTextRectangle(35, 0, 55, 0) : ''; // deviation: 4-6

        if (preg_match('/(?:\bSINCE|MEMBER[ ]*SINCE|VALID[ ]*THRU)[ ]*\S/i', $textFront)) { // MEMBER SINCE 03/2012    |    VALID THRU 02/2017
            if ($properties = $this->parseFormat_3($frontSide)) {
                return $properties;
            }
        }

        /**
         * @CardExample(accountId=1695563, cardUuid="ecc75950-bbea-41ad-b02d-497c6c8a2255", groupId="format4")
         * @CardExample(accountId=3756388, cardUuid="f12d2208-aa4c-4a45-b193-1e04c42552a3", groupId="format4")
         */
        $textFront = $frontSide ? $frontSide->getDOM(2)->getTextRectangle(0, 50, 0, 55) : ''; // deviation: 1-2

        if (preg_match('/(?:Member[ ]*since|Valid[ ]*through)[ ]*\S/i', $textFront)) { // Member since 12/2016    |    Valid through 02/2018
            if ($properties = $this->parseFormat_4($textFront)) {
                return $properties;
            }
        }

        /**
         * @CardExample(accountId=3840126, cardUuid="bc86833c-d958-4e5a-9270-a74f47f54cdb", groupId="format5")
         */
        $textFront = $frontSide ? $frontSide->getText(5) : ''; // deviation: 1-9

        if (preg_match('/Membership/i', $textFront)) { // Membership: 532575857A
            if ($properties = $this->parseFormat_5($textFront)) {
                return $properties;
            }
        }

        // etc.

        return $properties;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Account Number
            'Login2', // Last Name
        ];
    }

    private function parseFormat_1($frontSide) // obsolete!
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        /**
         * @CardExample(accountId=3513853, cardUuid="f6b7152b-3145-4d1e-847c-b06042a0becc", groupId="format1cc")
         * @CardExample(accountId=3181389, cardUuid="1c351150-5887-4b98-954f-31e1648bcbd7", groupId="format1cc")
         */
        $textFront = $frontSide ? $frontSide->getDOM(3)->getTextRectangle(0, 0, 55, 0) : ''; // deviation: 1-6

        // Number

        $textFrontConverted = str_replace(['.', ' '], '', $textFront);

        $textFrontConverted = preg_replace('/(.+)(?:Signature|VISA).*/i', '$1', $textFrontConverted);

        if (preg_match_all('/^([A-z\d][\d]{5,}[A-z\d])$/m', $textFrontConverted, $numberMatches)) {
            $properties['Login'] = $numberMatches[1][count($numberMatches[1]) - 1];
        }

        // Name & Last Name

        $textFrontConverted = preg_replace('/[^A-z\d,& \t\r\n\'\-\|]/ims', '', $textFront);

        if (preg_match('/^[ ]*([A-z][-\'A-z ]*)[ ]*$/m', $textFrontConverted, $matches)) {
            $names = $this->parsePersonName($matches[1]);
            $properties['Login2'] = $names['lastname'];
        }

        return $properties;
    }

    private function parseFormat_2($frontSide, $backSide, $textFullTopRightConverted)
    {
        // example accounts: 4258237,3349100,2817199,4127675,4058065,2315374,4291019

        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        // Number

        if (preg_match('/(?:WORLD[ ]*OF[ ]*HYATT[ ]*#|EXPLORIST|\bMEMBER|DISCOVERIST)$\s+^[ ]*(?<number>[A-z\d][\d]{5,}[A-z\d])$/im', $textFullTopRightConverted, $matches)) {
            $properties['Login'] = $matches['number'];
        }

        // Name & Last Name

        $textFrontBottom = $frontSide ? $frontSide->getDOM(4)->getTextRectangle(0, 0, 60, 0) : ''; // deviation: 0-6
        $textBackBottom = $backSide ? $backSide->getDOM(4)->getTextRectangle(0, 0, 60, 0) : ''; // deviation: 0-6

        $textFullBottom = $textFrontBottom . "\n" . $textBackBottom;

        if (preg_match('/^(?<fullName>[A-z][-.\'A-z ]*\b)[ ]+[\dOo][,.\dOo ]*$/m', $textFullBottom, $matches)) {
            $names = $this->parsePersonName($matches['fullName']);
            $properties['Login2'] = $names['lastname'];
        }

        return $properties;
    }

    private function parseFormat_3($frontSide)
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $patterns = [
            'nameBadSymbols' => '/[^A-z\d,& \t\r\n\'\-\|]/ims',
            'travellerName'  => '[A-z][-\'A-z ]*',
        ];

        $properties = [];

        // Number

        $textFront = $frontSide ? $frontSide->getDOM(5)->getTextRectangle(0, 0, 55, 0) : ''; // deviation: 4-6

        /**
         * @CardExample(accountId=3396377, cardUuid="60da2421-e0a4-4770-9fb5-e2b2853cbe98", groupId="format3")
         */
        $textFrontConverted = str_replace(['.', ' ', '凵', '口'], ['', '', 'U', '0'], $textFront);

        $textFrontConverted = preg_replace('/(.+)(?:\bSINCE|MEMBER[ ]*SINCE|VALID[ ]*THRU).*/i', '$1', $textFrontConverted);

        if (preg_match('/^(?<number>[A-z\d]\d{5,}[A-z\d])$/m', $textFrontConverted, $matches)) {
            $properties['Login'] = $matches['number'];
        }

        // Name & Last Name

        $deviation = 5;

        $textFront = $frontSide ? $frontSide->getDOM($deviation)->getTextRectangle(0, 0, 55, 20) : ''; // deviation: 4-6

        $in = ['凵', '口'];
        $out = ['U', 'O'];

        $textFrontConverted = str_replace($in, $out, $textFront);

        $textFrontConverted = preg_replace($patterns['nameBadSymbols'], '', $textFrontConverted);

        if (preg_match('/^[ ]*(?<name>' . $patterns['travellerName'] . ')[ ]*$/m', $textFrontConverted, $matches)) {
            /**
             * @CardExample(accountId=2368223, cardUuid="b9217261-c97e-428b-92a8-7e345eeb7498", groupId="format3")
             */
            $matches['name'] = preg_replace('/(.+)[ ]*VP$/i', '$1', $matches['name']);

            $names = $this->parsePersonName($matches['name']);
            $properties['Login2'] = $names['lastname'];
        }

        if (empty($properties['Login2'])) {
            /**
             * @CardExample(accountId=4289687, cardUuid="de953814-4b01-4c23-88d2-e01887216712", groupId="format3")
             */
            $textFront = $frontSide ? $frontSide->getDOM($deviation)->getTextRectangle(0, 0, 80, 0) : ''; // deviation: 4-6

            $textFrontConverted = str_replace($in, $out, $textFront);

            $textFrontConverted = preg_replace($patterns['nameBadSymbols'], '', $textFrontConverted);

            if (preg_match('/^[ ]*(?<name>' . $patterns['travellerName'] . ')[ ]*$/m', $textFrontConverted, $matches)) {
                $names = $this->parsePersonName($matches['name']);
                $properties['Login2'] = $names['lastname'];
            }
        }

        return $properties;
    }

    private function parseFormat_4($textFront)
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        // Number

        $textFrontConverted = str_replace(['.', ' ', '凵', '口'], ['', '', 'U', '0'], $textFront);

        if (preg_match('/^([A-z\d][\d]{5,}[A-z\d])$/m', $textFrontConverted, $matches)) {
            $properties['Login'] = $matches[1];
        }

        // Name & Last Name

        $textFrontConverted = str_replace(['凵', '口'], ['U', 'O'], $textFront);

        $textFrontConverted = preg_replace('/[^A-z\d,& \t\r\n\'\-\|]/ims', '', $textFrontConverted);

        if (preg_match('/^[ ]*([A-z][-\'A-z ]*)[ ]*$/m', $textFrontConverted, $matches)) {
            $names = $this->parsePersonName($matches[1]);
            $properties['Login2'] = $names['lastname'];
        }

        return $properties;
    }

    private function parseFormat_5($textFront)
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        // Number

        $textFrontConverted = str_replace('.', '', $textFront);

        if (preg_match('/\b([A-z\d][\d]{5,}[A-z\d])\b/', $textFrontConverted, $matches)) {
            $properties['Login'] = $matches[1];
        }

        return $properties;
    }

    private function hideCCNumber(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(0, 30, 100, 40)])
            ->setBack($rects);
    }

    private function parsePersonName($string = ''): array
    {
        $result = ['prefix' => '', 'firstname' => '', 'middlename' => '', 'lastname' => ''];
        $nameParts = preg_split('/[ ]+/', trim($string));

        if (count($nameParts) > 1) {
            $namePartFirst = mb_strtolower($nameParts[0]);
            $namePartLast = mb_strtolower($nameParts[count($nameParts) - 1]);

            foreach ($this->namePrefixes as $prefix) {
                $prefix = mb_strtolower(str_replace('.', '', $prefix));

                if ($namePartFirst === $prefix) {
                    $result['prefix'] = array_shift($nameParts);
                } elseif ($namePartLast === $prefix) {
                    $result['prefix'] = array_pop($nameParts);
                }
            }
        }

        if (count($nameParts) === 1) {
            $result['firstname'] = $nameParts[0];
        } elseif (count($nameParts) === 2) {
            $result['firstname'] = $nameParts[0];
            $result['lastname'] = $nameParts[1];
        } elseif (count($nameParts) === 3) {
            $result['firstname'] = $nameParts[0];
            $result['middlename'] = $nameParts[1];
            $result['lastname'] = $nameParts[2];
        } elseif (count($nameParts) > 3) {
            $result['firstname'] = $nameParts[0];
            $result['lastname'] = $nameParts[count($nameParts) - 1];
        }

        return $result;
    }
}
