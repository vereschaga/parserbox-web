<?php

namespace AwardWallet\Engine\spg\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserSpg implements CardImageParserInterface, CreditCardDetectorInterface
{
    protected $devMode = 0;

    protected $ccDetectionResult;

    protected $frontSide;
    protected $backSide;

    protected $patterns = [
        'number'    => '/(?:[^\d]|\b)([\dO]{9,11})\b/',
        'name'      => '\b[A-z][-\'A-z ]*\b',
        'stopWords' => '/\b(?:Sp[g8]|Starwood|Preferred|Guest|Plus|Gold|Platinum|Lifetime|Marriott|Rewards|MEMBER|SINCE|STARPOINTS|BALANCE)\b/i',
    ];

    // https://www.starwoodhotels.com/preferredguest/account/enroll/index.html?&language=en_US&localeCode=en_US
    protected $namePrefixes = ['Miss', 'Mrs.', 'Mr.', 'Ms.', 'Dr.'];

    /**
     * @Detector(version="2")
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
            $this->hideCCNumber($this->ccDetectionResult);
        }

        return $this->ccDetectionResult;
    }

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        if (!$this->frontSide) {
            $this->frontSide = $cardRecognitionResult->getFront();
        }

        if (!$this->backSide) {
            $this->backSide = $cardRecognitionResult->getBack();
        }

        if (!$this->frontSide && !$this->backSide) {
            return [];
        }

        // Detect Credit Card
        if ($this->detectCC_1()) {
            return [];
        }

        /**
         * @CardExample(accountId=3982907, cardUuid="973ab822-d859-45f7-af34-996080cc0eab", groupId="format1")
         * @CardExample(accountId=2565759, cardUuid="a2bafd33-8846-46cf-b295-e13b7ac67c65", groupId="format1")
         * @CardExample(accountId=3771746, cardUuid="add1fbbf-efcf-4123-a4ae-f89db975748f", groupId="format1")
         * @CardExample(accountId=1915237, cardUuid="1c6733c9-706b-4a75-8517-0fad06606ef1", groupId="format1")
         */
        $textFrontLeftMiddle = $this->frontSide ? $this->frontSide->getDOM(3)->getTextRectangle(0, 60, 25, 25) : ''; // deviation: 1-4

        if (preg_match('/.{8}[\dO]$\s+^Member[ ]*Since[ ]*[12]...$/mi', $textFrontLeftMiddle)) { // 45283521287 \n Member Since 2017
            if ($properties = $this->parseFormat_1($textFrontLeftMiddle)) {
                return $properties;
            }
        }

        /**
         * @CardExample(accountId=4074072, cardUuid="254cc17a-5e8a-4179-ac60-89f17cd1eb3a", groupId="format2")
         * @CardExample(accountId=4003098, cardUuid="5680dd12-8a8d-4316-9913-d7d7de6f51b4", groupId="format2")
         * @CardExample(accountId=4015438, cardUuid="080ddf2f-9468-4e24-90cb-ab02934675c6", groupId="format2")
         */
        $textFrontLeftBottom = $this->frontSide ? $this->frontSide->getDOM(2)->getTextRectangle(0, 60, 50, 20) : ''; // deviation: 1-4
        $patternKeywords = implode('|', ['MEMBEI?[RP]', 'SINCE', 'SING']); // MEMBER SINCE 2011    |    MEMBEIP SINCE 2011
        $condition1 = preg_match('/\b(?:' . $patternKeywords . ')\b/i', $textFrontLeftBottom) > 0;
        $textFrontLeftBottomConverted = preg_replace('/\b(?:' . $patternKeywords . '|[\d\s]+)\b/i', '', $textFrontLeftBottom);
        $condition2 = $textFrontLeftBottomConverted === '';

        if ($condition1 && $condition2) {
            if ($properties = $this->parseFormat_2()) {
                return $properties;
            }
        }

        /**
         * @CardExample(accountId=3995288, cardUuid="fbe2238f-3f61-4090-ac8a-4dbc9c4b2282", groupId="format2")
         */
        $textFrontRightBottom = $this->frontSide ? $this->frontSide->getDOM(1)->getTextRectangle(60, 0, 50, 20) : ''; // deviation: 0-2
        $condition1 = preg_match('/\b(?:' . $patternKeywords . ')\b/i', $textFrontRightBottom) > 0;
        $textFrontRightBottomConverted = preg_replace('/\b(?:' . $patternKeywords . '|[\d\s]+)\b/i', '', $textFrontRightBottom);
        $condition2 = $textFrontRightBottomConverted === '';

        if ($condition1 && $condition2) {
            if ($properties = $this->parseFormat_2()) {
                return $properties;
            }
        }

        /**
         * @CardExample(accountId=3748735, cardUuid="90b9ace7-815c-4ac2-b5c1-3e01db80f2f9", groupId="format3")
         * @CardExample(accountId=4016281, cardUuid="8517fdf1-794f-439d-a599-4bccae534cbc", groupId="format3")
         * @CardExample(accountId=4083835, cardUuid="5978c215-5b64-4862-ac14-5712fd27d6b2", groupId="format3")
         * @CardExample(accountId=4090384, cardUuid="6d6eafdd-a0f0-4bde-800f-34b41780b278", groupId="format3")
         * @CardExample(accountId=3922082, cardUuid="05d48a1f-98e7-45b7-9be0-6c592cb8505e", groupId="format3")
         * @CardExample(accountId=4009116, cardUuid="6c451c6f-9b5e-4510-b337-63794206ef5e", groupId="format3")
         * @CardExample(accountId=4042752, cardUuid="f0ebc84b-14ec-4bb7-a59c-2c13028bd3de", groupId="format3")
         * @CardExample(accountId=1957882, cardUuid="7d1c33f8-344a-4688-a5b8-fe31b73b797b", groupId="format3")
         */
        $textFrontLeftMiddle = $this->frontSide ? $this->frontSide->getDOM(10)->getTextRectangle(0, 40, 0, 30) : ''; // deviation: 7-34
        $textFrontRightMiddle = $this->frontSide ? $this->frontSide->getDOM(10)->getTextRectangle(60, 0, 0, 30) : ''; // deviation: 7-34
        $s = '[. ]*';
        $pattern1 = "/S{$s}T{$s}A{$s}R{$s}W{$s}O{$s}O?{$s}D?"
            . "\s*P?{$s}R?{$s}E?{$s}F?{$s}E{$s}R{$s}R{$s}E{$s}D/i"; // STAR . W O O D PREFERRED    |    STARWO ERRED
        $pattern2 = "/\b[Ga]{$s}U{$s}E{$s}S{$s}T?\b/i"; // GUEST    |    GUES    |    aUE ST
        $condition1 = preg_match($pattern1, $textFrontLeftMiddle) > 0;
        $condition2 = preg_match($pattern2, $textFrontRightMiddle) > 0;

        if ($condition1 && $condition2) {
            if ($properties = $this->parseFormat_3($pattern1, $pattern2)) {
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
            'Login2', // Last Name
            //            'Preferred', // AccountStatus
        ];
    }

    protected function parseFormat_1($textFrontLeftMiddle)
    {
        // example accounts: 4059051

        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        // Number

        $textFrontLeftMiddleConverted = str_replace(['.', ' '], '', $textFrontLeftMiddle);

        /**
         * @CardExample(accountId=3293281, cardUuid="89d3538b-2593-4d49-bfba-953be1eb986b", groupId="format1")
         */
        $textFrontLeftMiddleConverted = str_replace(['O'], ['0'], $textFrontLeftMiddleConverted);

        if (preg_match($this->patterns['number'], $textFrontLeftMiddleConverted, $matches)) {
            $properties['Login'] = $matches[1];
        }

        // Name & Last Name

        $textFrontLeftTop = $this->frontSide ? $this->frontSide->getDOM(7)->getTextRectangle(0, 40, 0, 60) : ''; // deviation: 0-12

        /**
         * @CardExample(accountId=3963663, cardUuid="20badda9-1656-4465-8d42-a9fc0a7e7ad7", groupId="format1")
         */
        $textFrontLeftTopConverted = preg_replace($this->patterns['stopWords'], '', $textFrontLeftTop);

        $pattern = '/'
            . '(?:^|' . $this->patterns['name'] . '\s*\n)'  // Name
            . '\s*(' . $this->patterns['name'] . ')\s*'     // Last Name
            . '(?:$|\n[\dO].{8})'
            . '/';

        if (preg_match($pattern, $textFrontLeftTopConverted, $matches)) {
            $names = preg_split('/[ ]+/', $matches[1]);
            $lastName = $names[count($names) - 1];
            $properties['Login2'] = strtoupper($lastName);
        }

        return $properties;
    }

    protected function parseFormat_2()
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        $textFrontLeftTop = $this->frontSide ? $this->frontSide->getDOM(2)->getTextRectangle(0, 50, 0, 50) : ''; // deviation: 1-4
        $textFrontRightTop = $this->frontSide ? $this->frontSide->getDOM(1)->getTextRectangle(50, 0, 0, 50) : ''; // deviation: 1

        $textFrontLeftTopConverted = str_replace('.', '', $textFrontLeftTop);
        $textFrontRightTopConverted = str_replace('.', '', $textFrontRightTop);

        // Number

        if (preg_match($this->patterns['number'], $textFrontLeftTopConverted, $matches)) {
            $properties['Login'] = $matches[1];
        } elseif (preg_match($this->patterns['number'], $textFrontRightTopConverted, $matches)) {
            $properties['Login'] = $matches[1];
        }

        // Name & Last Name

        if (!empty($properties['Login'])) {
            if (preg_match('/^\s*(' . $this->patterns['name'] . ')\s*' . $properties['Login'] . '/', $textFrontLeftTopConverted, $matches)) {
                $matches[1] = str_replace(["\n", '  '], ' ', $matches[1]);
                $names = $this->parsePersonName($matches[1]);
                $properties['Login2'] = $names['lastname'];
            } elseif (preg_match('/^\s*(' . $this->patterns['name'] . ')\s*' . $properties['Login'] . '/', $textFrontRightTopConverted, $matches)) {
                $matches[1] = str_replace(["\n", '  '], ' ', $matches[1]);
                $names = $this->parsePersonName($matches[1]);
                $properties['Login2'] = $names['lastname'];
            }
        }

        return $properties;
    }

    protected function parseFormat_3($pattern1 = '', $pattern2 = '')
    {
        // example accounts: 4090384,3930361,4008469,3954636,4095966,4105977,4083835,4009116,3999269,4016281,4042752,3421424,3378498,1721776,707801,2348472,1957882,3930991,3877285,3895850,3881017,3928627,3925787,3922082,3766582,3748735,3842921

        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        $textFrontLeftBottom = $this->frontSide ? $this->frontSide->getDOM(3)->getTextRectangle(0, 50, 60, 0) : ''; // deviation: 2-5

        $textFrontLeftBottomConverted1 = preg_replace([$pattern1, $pattern2], '', $textFrontLeftBottom);

        // Number

        $textFrontLeftBottomConverted2 = str_replace(['.', '-', ' '], '', $textFrontLeftBottomConverted1);

        if (preg_match($this->patterns['number'], $textFrontLeftBottomConverted2, $matches)) {
            $properties['Login'] = $matches[1];
        }

        // Name & Last Name

        $textFrontLeftBottomConverted3 = str_replace(['.', ':'], '', $textFrontLeftBottomConverted1);

        if (preg_match('/^\s*(' . $this->patterns['name'] . ')/', $textFrontLeftBottomConverted3, $matches)) {
            $names = $this->parsePersonName($matches[1]);
            $properties['Login2'] = $names['lastname'];
        }

        return $properties;
    }

    protected function parseFormat_999()
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $result = [];

        $textFront = $this->frontSide ? $this->frontSide->getText() : '';
        $textBack = $this->backSide ? $this->backSide->getText() : '';

        // FRONT

        if ($textFront) {
            $noSpaces = str_replace(' ', '', $textFront);

            if (preg_match('/(?:\D|^)([\dO]{7,11})\w*(?:[^\w]|$)/m', $noSpaces, $m)) {
                $result['Login'] = str_replace('O', '0', $m[1]);
                $number = $m[1];
            }

//            if (null !== $cardText->findPreg('#(Corporate)#i')) {
//                $result['Preferred'] = 'Corporate';
//            } elseif (null !== $cardText->findPreg('#(BUSINESS)#i')) {
//                $result['Preferred'] = 'Business';
//            } elseif (null !== $cardText->findPreg('#(Guest)#i')) {
//                $result['Preferred'] = 'Guest';
//            }
            if (isset($number)) {
                $numberSpaces = implode(" *", str_split($number));
            }

            $textFrontConverted = str_replace(':', '', $textFront);

            $textFrontConverted = preg_replace($this->patterns['stopWords'], '', $textFrontConverted);

            if (
                preg_match('/^\s*([A-Z][-\'A-z ]*[A-z])\n.*?\s*([A-z]{2,}.*?)\n/s', $textFrontConverted, $m)
                && stripos($m[1], 'Starwood') === false
                && stripos($m[1], 'PREFERRED') === false
            ) {
                if (in_array($m[2], ['SP', 'STARWOOD'])) {
                    $name = $m[1];
                } else {
                    $name = $m[1] . ' ' . $m[2];
                }

                if (!preg_match('/\d+/', $name) && $this->excludeWords($name)) {
                    $result['Login2'] = $name;
                }

                if (false !== stripos($m[1], 'name')) {
                    $result['Login2'] = trim(preg_replace(['/name/i', '/\d+/'], ['', ''], $m[2]));
                }
            }

            if (
                empty($result['Login2'])
                && isset($numberSpaces)
                && preg_match("#^([^\n]+?)\s*(?:spg.*?|$)\n[^\n]*?{$numberSpaces}#mi", $textFront, $m)
            ) {
                $name = $m[1];

                if (!preg_match('/\d+/', $name) && $this->excludeWords($name)) {
                    $result['Login2'] = $name;
                }
            }

            if (
                empty($result['Login2'])
                && isset($number)
                && ($name = $this->frontSide->getDOM(5)->findSingleNode("//div[contains(translate(.,' ',''), '{$number}')]/preceding-sibling::div[1]", null, "/^(\D+?)\s*(?:spg|$)/i")) !== null
            ) {
                if (!preg_match('/\d+/', $name) && $this->excludeWords($name)) {
                    $result['Login2'] = $name;
                }
            }

            if (!empty($result['Login2']) && strlen($result['Login2']) < 4) {
                unset($result['Login2']);
            }
        }

        // BACK

        if (empty($result['Login']) && $textBack) {
            $noSpaces = str_replace(' ', '', $textBack);

            if (preg_match('/(?:\D|^)(\d{9,11})(?:[^\w]|$)/', $noSpaces, $m)) {
                $result['Login'] = $m[1];
            }
        }

        if (!empty($result['Login2']) && is_string($result['Login2'])) {
            $names = $this->parsePersonName($result['Login2']);
            $result['Login2'] = strtoupper($names['lastname']);
        }

        return $result;
    }

    protected function detectCC_1(): bool
    {
        // example accounts: 3970849,3307324

        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $patterns = [
            'image' => '/(American\s*Express|VISA|Credit\s*card)/i',
            //            'text' => '/(?:)/i',
        ];

        /**
         * @CardExample(accountId=3970849, cardUuid="6df7988b-1565-4cf7-b5e0-189f19883e52", groupId="formatCC1")
         */

        // FRONT

        if ($this->frontSide) {
            $frontLogos = $this->frontSide->getDOM(0)->findNodes('/img[@left > 50]/@alt', null, $patterns['image']);
            $frontLogoValues = array_values(array_filter($frontLogos));

            if (!empty($frontLogoValues[0])) {
                return true;
            }

            $textFrontBottom = $this->frontSide->getDOM(0)->getTextRectangle(50, 0, 50, 0); // deviation: 0-1

            if (preg_match('/\bAMEX\b/', $textFrontBottom)) {
                return true;
            }

            $textFront = $this->frontSide->getText(); // deviation: default
        } else {
            $textFront = '';
        }

        // BACK

        if ($this->backSide) {
            $backLogos = $this->backSide->getDOM(0)->findNodes('/img[@left > 50]/@alt', null, $patterns['image']);
            $backLogoValues = array_values(array_filter($backLogos));

            if (!empty($backLogoValues[0])) {
                return true;
            }

            $textBack = $this->backSide->getText(); // deviation: default
        } else {
            $textBack = '';
        }

        // FULL

        if (preg_match('/(?:american|express|tenational Direct|mastercard|Valid Thru|open\.com)/i', $textFront . $textBack)) {
            return true;
        }

        /**
         * @CardExample(accountId=3307324, cardUuid="b425f508-1948-4a58-be09-1b672191e86e", groupId="formatCC1")
         */
        // American Express consists of only 15 digits, divided into groups of 4-6-5 characters
        if (preg_match('/\b\d{4} \d{6} \d{5}\b/', $textFront . $textBack) && preg_match('/\sAME/i', $textFront . $textBack)) {
            return true;
        }

        return false;
    }

    protected function hideCCNumber(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(0, 30, 100, 40)])
            ->setBack($rects);
    }

    protected function excludeWords($name)
    {
        return !preg_match("#\bGuest\b#i", $name)
            && !preg_match("#\bSPG\b#i", $name)
            && !preg_match("#\bStarwood\b#i", $name)
            && !preg_match("#\bValid\b#i", $name)
            && !preg_match("#\bNombre\b#i", $name)
            && !preg_match("#\bName\b#i", $name)
            && !preg_match("#\bAMERICAN\b#i", $name)
            && !preg_match("#\bBusiness\b#i", $name);
    }

    protected function parsePersonName(string $string): array
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
