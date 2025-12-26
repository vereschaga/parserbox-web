<?php

namespace AwardWallet\Engine\rentacar\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;

class CardImageParserRentacar implements CardImageParserInterface
{
    protected $devMode = 0;

    protected $frontSide;
    protected $backSide;

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        $this->frontSide = $cardRecognitionResult->getFront();

        if (!$this->frontSide) {
            return [];
        }

        $textFront = $this->domToText('front', 5, '@top < 50'); // deviation: 1-9

        if (preg_match('/\b[Mm]ember[ ]*#?[ ]*:?[ ]*[A-Z\d]{7}[ ]*$/mi', $textFront)) { // Member # : 216BGTQ
            if ($properties = $this->parseFormat_1($textFront)) {
                return $properties;
            }
        }

        $textFront = $this->domToText('front', 10, '@top > 55'); // deviation: 4-16

        if (preg_match('/\d[ ]*points?[ ]*to[ ]*date/i', $textFront)) { // 0 points to date
            if ($properties = $this->parseFormat_2()) {
                return $properties;
            }
        }

        $textFront = $this->frontSide->getDOM(3)->getTextRectangle(0, 30, 70, 0); // deviation: 1-6

        if (preg_match('/[Mm]ember[ ]*[Nn]umber/i', $textFront)) { // member number
            if ($properties = $this->parseFormat_3($textFront)) {
                return $properties;
            }
        }

        $textFront = $cardRecognitionResult->getFront()->getText();

        if (preg_match('/member since 20\d{2}/', $textFront)) { // member since
            if ($properties = $this->parseFormat_5($textFront)) {
                return $properties;
            }
        }

        $textFront = $this->domToText('front', 5, '@top > 75'); // deviation: 5
        $condition1 = preg_match('/(\bMEM[A-Z]{3,}|SINCE)/', $textFront) > 0; // MEMBER SINCE 2017
        $frontLogos = $this->frontSide->getDOM(0)->findNodes('/img[@left > 20 and @left < 50 and @top > 20 and @top < 50]/@alt', null, '/(?:Enterprise\s*Holdings|Enterprise\s*Fleet\s*Management)/i');
        $frontLogoValues = array_values(array_filter($frontLogos));
        $condition2 = !empty($frontLogoValues[0]);
        $condition3 = count($this->frontSide->getDOM(0)->findNodes('//span[@left > 20 and @left < 50 and @top > 20 and @top < 50 and (normalize-space()="enterprise" or normalize-space()="nterprise")]')) > 0;
        $textFront = $cardRecognitionResult->getFront()->getText();

        if ($condition1 || $condition2 || $condition3) {
            if ($properties = $this->parseFormat_4($textFront)) {
                return $properties;
            }
        }

        // etc.

        return $properties;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Member Number
        ];
    }

    protected function parseFormat_1($textFront)
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        /**
         * @CardExample(accountId=3933274, cardUuid="f3da4624-64b4-438e-9bd6-cce2fd220984", groupId="format1")
         * @CardExample(accountId=4576543, cardUuid="9cb09909-b0f8-41cb-a8e0-1b77c5f204cd", groupId="format1")
         */
        $textFront = $this->normalizeText($textFront);

        $pattern = '/'
            . '[Mm]ember[ ]*#?[ ]*:?[ ]*(?<number>[A-Z\d]{7})[ ]*$'	// Number
            . '/m';

        if (preg_match($pattern, $textFront, $m)) {
            $properties['Login'] = $m['number'];
        }

        return $properties;
    }

    protected function parseFormat_2()
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        /**
         * @CardExample(accountId=3946516, cardUuid="1c2ec472-839e-4a6f-b09a-dcf796d6684b", groupId="format2")
         * @CardExample(accountId=3861601, cardUuid="ba9c793f-f3e5-443e-aa82-12e615d653af", groupId="format2")
         */
        $textFront = $this->domToText('front', 6, '@top < 40'); // deviation: 2-10

        $textFront = $this->normalizeText($textFront);

        $textFront = str_ireplace('Plus', '', $textFront);

        $pattern = '/'
            . '^[ ]*[A-Z][-\'A-Z ]*[A-Z][ ]*$'       // Name & Last Name
            . '\s+^[ ]*#?(?<number>[A-Z\d]{7})[ ]*$' // Number
            . '/m';

        if (preg_match($pattern, $textFront, $m)) {
            $properties['Login'] = $m['number'];
        }

        return $properties;
    }

    protected function parseFormat_3($textFront)
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        /**
         * @CardExample(accountId=3951162, cardUuid="e748af55-93c2-4f41-a083-d2e0b5d34973", groupId="format3")
         * @CardExample(accountId=3919426, cardUuid="6c91c42a-9034-4f91-8bf3-2a9c5a2ca17d", groupId="format3")
         * @CardExample(accountId=3803919, cardUuid="d97c694f-b2fc-49f5-a795-ab7cd70a9d23", groupId="format3")
         */
        $textFront = str_replace(['.', ':'], '', $textFront);

        $textFront = str_ireplace(['Plus', 'your', 'Life'], '', $textFront);

        $pattern = '/'
            . '^[ ]*(?<number>[A-Z\d]{7})(?: |$)' // Number
            . '\s+^[ ]*[Mm]ember[ ]*[Nn]umber'    // member number
            . '/m';

        if (preg_match($pattern, $textFront, $matches)) {
            $properties['Login'] = $matches['number'];
        }

        return $properties;
    }

    protected function parseFormat_4($textFront)
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        /**
         * @CardExample(accountId=3865414, cardUuid="d810057d-f435-4bdf-826e-ce0a33bc3bc1", groupId="format4")
         * @CardExample(accountId=4118605, cardUuid="6dc83ccd-6cf5-471c-befc-8e9d1dbbbb2b", groupId="format4")
         * @CardExample(accountId=3032169, cardUuid="31734e25-0afa-424c-9435-a835d17ecafd", groupId="format4")
         * @CardExample(accountId=3893768, cardUuid="c57b0c85-35e2-4c69-98b5-030df6ccee55", groupId="format4")
         * @CardExample(accountId=3746766, cardUuid="9e5bad09-ddc8-4f8a-bee2-f8c8309d3de8", groupId="format4")
         */
        $textFrontMiddle = $this->frontSide ? $this->frontSide->getDOM(4)->getTextRectangle(0, 0, 50, 15) : ''; // deviation: 4-5

        $textFrontMiddleNormal = $this->normalizeText($textFrontMiddle);

        $pattern = '/'
            . '^[ ]*[A-z][-\'A-z ]*[A-z][ ]*$'     // Name & Last Name
            . '\s+^[ ]*(?<number>[A-Z\d]{7})[ ]*$' // Number
            . '/m';

        if (preg_match($pattern, $textFrontMiddleNormal, $matches)
                && !preg_match("/^.*ER20\d{2}/", $matches['number'])) { //159071
            $properties['Login'] = $matches['number'];
        } elseif (preg_match('/\n[A-Z\s\-?]+\n(?<number>[A-Z\d]{7})$/', $textFront, $matches)) { //4932080
            $properties['Login'] = $matches['number'];
        }

        return $properties;
    }

    protected function parseFormat_5($textFront)
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        /**
         * @CardExample(accountId=4219957, cardUuid="137e6cc1-7ae6-4cfc-bc9d-e4733ec7fd25", groupId="format5")
         */
        $pattern = '/'
            . '\n[A-Z ]+\n+' // name
            . 'member since 20\d{2}\n+'    // member number
            . '(?<number>[A-Z\d]{7})(?:\n|$)/';

        if (preg_match($pattern, $textFront, $matches)) {
            $properties['Login'] = $matches['number'];
        }

        return $properties;
    }

    protected function normalizeText($string)
    {
        /**
         * @CardExample(accountId=2565527, cardUuid="65585c30-ba3a-4691-8abb-160d4c60677b", groupId="formatAllNormalizeText")
         */
        $string = str_replace(['.', ':', ' '], '', $string);

        return $string;
    }

    protected function domToText($side = 'front', $deviation = 7, $xpathRule = '')
    {
        if ($side === 'front' && $this->frontSide) {
            $dom = $this->frontSide->getDOM($deviation);
        } elseif ($side === 'back' && $this->backSide) {
            $dom = $this->backSide->getDOM($deviation);
        } else {
            return null;
        }

        $rule = $xpathRule ? '[' . $xpathRule . ']' : '';

        $rowTexts = [];
        $rowNumber = 1;
        $maxRows = 100;

        while ($rowNumber < $maxRows) {
            if ($textRow = $dom->findSingleNode('//div' . $rule . '[' . $rowNumber . ']')) {
                $rowTexts[] = $textRow;
            } else {
                break;
            }
            $rowNumber++;
        }

        return implode("\n", $rowTexts);
    }
}
