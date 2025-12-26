<?php

namespace AwardWallet\Engine\ichotelsgroup\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;
use AwardWallet\CardImageParser\ImageRecognitionResult;

class CardImageParserIchotelsgroup implements CardImageParserInterface, CreditCardDetectorInterface
{
    protected $devMode = 0;

    protected $ccDetectionResult;

    /** @var ImageRecognitionResult */
    protected $frontSide;
    /** @var ImageRecognitionResult */
    protected $backSide;

    protected $namePrefixes = ['miss', 'mrs', 'mr', 'ms', 'dr'];

    protected $stopKeyWords = [
        'name' => ['Welcome', 'IHG', 'Rewards', 'Club', 'CLUB', 'Member', 'PLATINUM', 'ELITE', 'INTERCONTINENTAL', 'AMBASSADOR', 'I INTERCONTINENTAL'],
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
            $this->hideCCNumber($this->ccDetectionResult);
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

        $textFront = $this->frontSide ? $this->frontSide->getText() : '';
        $textFront = str_replace('.', '', $textFront);
        $textBack = $this->backSide ? $this->backSide->getText() : '';
        $textFull = $textFront . "\n" . $textBack;

        if (preg_match('/\bEXP\s*:/i', $textFront)) { // EXP: 31 DEC 2018
            if ($properties = $this->parseFormat_1($textFront)) {
                return $properties;
            }
        }

        if (preg_match('/\bEXP\s*[1920]{2}\n?\d{2}\s+.+/i', $textFront)) { // EXP 2014
            if ($properties = $this->parseFormat_2($textFront)) {
                return $properties;
            }
        }

        if (preg_match('/\s+MEMBER[ ]*SINCE[ ]*\d{4}\b/i', $textFront)) { // MEMBER SINCE 2007
            if ($properties = $this->parseFormat_3($textFront)) {
                return $properties;
            }
        }

        if (preg_match('/\bAMBASSADOR\b/', $textFront)) { // AMBASSADOR
            if ($properties = $this->parseFormat_4($textFront)) {
                return $properties;
            }
        }

        if (preg_match('/Rewards.+(?:Club|CLUB)\b/s', $textFront)) { // Rewards .. Club
            if ($properties = $this->parseFormat_5($textFront)) {
                return $properties;
            }
        }

        if (preg_match('/\s+[Cc]LUB\b/i', $textFront)) { // CLUB    |    cLUB
            if ($properties = $this->parseFormat_6($textFront)) {
                return $properties;
            }
        }

        if (preg_match('/\s+[Cc]LUB\b/i', $textFront)) { // CLUB    |    cLUB
            if ($properties = $this->parseFormat_7($textFront)) {
                return $properties;
            }
        }

        if (preg_match('/Member[ ]*Number\s*:?\s*\d{9,}(?:\D|\b)/i', $textFull)) { // Member Number
            if ($properties = $this->parseFormat_99($textFull)) {
                return $properties;
            }
        }

        // Other Formats
        if ($properties = $this->parseFormat_999($textFront)) {
            return $properties;
        }

        return $properties;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Email or Member #
            'Login2', // Last Name
        ];
    }

    protected function parseFormat_1($textFront)
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        /**
         * @CardExample(accountId=1146389,cardUuid="24cd628b-9783-4de1-aff7-9216f176b2eb",groupId="format1")
         */
        $pattern = '/'
            . '^[ ]*[A-z][-\'A-z ]*[ ]*$'		// Name
            . '\s+^[ ]*([A-z][-\'A-z ]*)[ ]*$'	// Last Name
            . '\s+^[ ]*([\d ]{5,}\d\S?)[ ]*$'        // Number
            . '\s+^[ ]*EXP\s*:'					// EXP: 31 DEC 2018
            . '/mi';

        if (preg_match($pattern, $textFront, $matches)) {
            $properties['Login'] = str_replace([' ', 'O'], ['', '0'], $matches[2]);
            $properties['Login2'] = $matches[1];
        }

        return $properties;
    }

    protected function parseFormat_2($textFront)
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        /**
         * @CardExample(accountId=1171413,cardUuid="9307147e-e631-4f6d-af0f-08c57e78f32b",groupId="format2")
         */
        $pattern = '/'
            . '\s+(?<fullName>[A-z][-\'A-z ]*[A-z])'    // Name & Last Name
            . '\s+EXP\s*[1920]{2}\n?\d{2}'              // EXP 2014
            . '\s+(?<number>[\d ]{5,}\d)\s*$'           // Number
            . '/i';

        if (preg_match($pattern, $textFront, $matches)) {
            $properties['Login'] = str_replace(' ', '', $matches['number']);
            $names = $this->parsePersonName($matches['fullName']);
            $properties['Login2'] = $names['lastname'];
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
         * @CardExample(accountId=2524445,cardUuid="7931f57a-dbeb-40e0-bf01-0b807944956d",groupId="format3")
         */
        $pattern = '/'
            . '\s+([\d ]{5,}\d)'				// Number
            . '\s+([A-z][-\'A-z ]*[A-z])'		// Name & Last Name
            . '\s+MEMBER[ ]*SINCE[ ]*\d{4}\b'	// MEMBER SINCE 2007
            . '/i';

        if (preg_match($pattern, $textFront, $matches)) {
            $properties['Login'] = str_replace(' ', '', $matches[1]);
            $names = $this->parsePersonName($matches[2]);
            $properties['Login2'] = $names['lastname'];
        }

        /**
         * @CardExample(accountId=3913480,cardUuid="8b3f1a6f-6a32-4921-8707-ef66b5bfa001",groupId="format3")
         */
        $pattern = '/'
            . '\b([A-z][-\'A-z ]*[A-z])[ ]*$'			// Name & Last Name
            . '\s+^[ ]*MEMBER[ ]*SINCE[ ]*\d{4}[ ]*$'	// MEMBER SINCE 2007
            . '\s+^[ ]*([\d ]{5,}\d)[ ]*$'				// Number
            . '/mi';

        if (preg_match($pattern, $textFront, $matches)) {
            $properties['Login'] = str_replace(' ', '', $matches[2]);
            $names = $this->parsePersonName($matches[1]);
            $properties['Login2'] = $names['lastname'];
        }

        return $properties;
    }

    protected function parseFormat_4($textFront)
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        $textFront = str_replace(['Spire', '①', 'pire', 'в‘'], ['', '', '', ''], $textFront);

        $patterns['nameStopKeyWords'] = '(' . implode('|', $this->stopKeyWords['name']) . ')';

        /**
         * @CardExample(accountId=2734757,cardUuid="acad375b-a766-42ac-9121-75c6f4ed9486",groupId="format4")
         * @CardExample(accountId=2710132,cardUuid="63a61067-3250-411e-b30b-fd5d1169f4a4",groupId="format4")
         */
        $pattern = '/'
            . '^[ ]*AMBASSADOR[ ]*$'                                 // AMBASSADOR
            . '\s+^[ ]*[A-z][-\'A-z ]*[ ]*$'                         // Name
            . '\s+^[ ]*(?<lastName>[A-z][-\'A-z ]*)[ ]*$'            // Last Name
            . '(?:\s+^[ ]*\d[,\d]*[ ]+POINTS[ ]*$)?'                 // 136,138 POINTS
            . '\s+^[ ]*(?:Gold|GOLD|Platinum|Spire)[ ]*$'            // GOLD    |    Platinum
            . '(?:\s+^[ ]*(?<numberTop>[\d ]{5,}\d)[ ]*$)?'          // Number (TOP)
            . '\s+^[ ]*(?:AMBASSADOR[ ]+)?EXP:?\b.* \d{2,4}[ ]*$'    // AMBASSADOR EXP 01 OCT 2017    |    EXP: 31 Jul 19
            . '(?:\s+^[ ]*(?<numberBottom>[\d ]{5,}\d)[ ]*$)?'       // Number (BOTTOM)
            . '/m';

        if (preg_match($pattern, $textFront, $matches) && (!empty($matches['numberTop']) || !empty($matches['numberBottom']))) {
            $number = !empty($matches['numberTop']) ? $matches['numberTop'] : $matches['numberBottom'];
            $properties['Login'] = str_replace(' ', '', $number);
            $properties['Login2'] = $matches['lastName'];

            return $properties;
        }

        /**
         * @CardExample(accountId=2042574,cardUuid="d343b90b-b8dd-469b-9e6f-1f6a886aadd0",groupId="format4")
         */
        $pattern = '/'
            . '\s+^[ ]*(?<fullName>[A-z][-\'A-z ]*[A-z])[ ]*$'    // Name & Last Name
            . '\s+^[ ]*AMBASSADOR[ ]+EXP:?\b.* \d{4}[ ]*$'        // AMBASSADOR EXP 01 OCT 2017
            . '\s+^[ ]*(?<number>[\d ]{5,}\d)[ ]*$'               // Number
            . '/m';

        if (preg_match($pattern, $textFront, $matches)) {
            $properties['Login'] = str_replace(' ', '', $matches['number']);
            $names = $this->parsePersonName($matches['fullName']);
            $properties['Login2'] = $names['lastname'];
        }

        /**
         * @CardExample(accountId=2069369,cardUuid="b24f3ceb-96e7-46b9-8480-dae1097dbe20",groupId="format4")
         */
        $pattern = '/'
            . '\s+^[ ]*(?<number>[\d ]{5,}\d)[ ]*$'               // Number
            . '\s+^[ ]*(?<fullName>[A-z][-\'A-z ]*[A-z])[ ]*$'    // Name & Last Name
            . '\s+^[ ]*AMBASSADOR[ ]+EXP:?\b.* \d{4}[ ]*$'        // AMBASSADOR EXP 01 OCT 2017
            . '/m';

        if (preg_match($pattern, $textFront, $matches)) {
            $properties['Login'] = str_replace(' ', '', $matches['number']);
            $matches['fullName'] = trim(preg_replace(['/\b' . $patterns['nameStopKeyWords'] . '\b/i', '/\s+/'], ['', ' '], $matches['fullName']));
            $names = $this->parsePersonName($matches['fullName']);
            $properties['Login2'] = $names['lastname'];
        }

        return $properties;
    }

    protected function parseFormat_5($textFront)
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        $patterns['nameStopKeyWords'] = '(' . implode('|', $this->stopKeyWords['name']) . ')';

        /**
         * @CardExample(accountId=3866691,cardUuid="7204888e-6f4c-4b4a-a06a-ce45e0566941",groupId="format5")
         * @CardExample(accountId=3757673,cardUuid="c663069a-4dd2-4b24-933a-ba7615c1d822",groupId="format5")
         * @CardExample(accountId=4126469,cardUuid="f942e2b4-9c82-4c91-9ae4-d0d2e9e1d541",groupId="format5")
         */
        $pattern = '/^'
            . '[ ]*(?<name>[A-z][-\'A-z ]*)\n'          // Name
            . '(?:[ ]*(?<lastName>[A-z][-\'A-z ]*)\n)?' // Last Name
            . '[ ]*(?<number>[\d ]{5,}\d)[ ]*\n'        // Number
            . '.*'
            . 'Rewards.+(?:Club|CLUB)\b'                // Rewards .. Club
            . '/s';

        if (preg_match($pattern, $textFront, $matches)) {
            $properties['Login'] = str_replace(' ', '', $matches['number']);
            $name = !empty($matches['lastName']) ? $matches['lastName'] : $matches['name'];

            if (!preg_match('/\b' . $patterns['nameStopKeyWords'] . '\b/i', $name)) {
                if (!empty($matches['lastName'])) {
                    $properties['Login2'] = $name;
                } else {
                    $names = $this->parsePersonName($name);
                    $properties['Login2'] = $names['lastname'];
                }
            }
        }

        /**
         * @CardExample(accountId=2530543,cardUuid="9640884e-98ea-4898-9496-188d66ee346f",groupId="format5")
         * @CardExample(accountId=3772406,cardUuid="f0eaa611-36f2-404b-b76f-daedddcbe613",groupId="format5")
         */
        $pattern = '/'
            . 'Rewards.+(?:Club|CLUB)\b'                    // Rewards .. Club
            . '.*'
            . '\n(?<fullName>[A-z][-\'A-z ]*[A-z])[ ]*\n'   // Name & Last Name
            . '[ ]*(?<number>[\d ]{5,}\d)[ ]*(?:\n|$)'      // Number
            . '/s';

        if (preg_match($pattern, $textFront, $matches)) {
            $properties['Login'] = str_replace(' ', '', $matches['number']);

            if (!preg_match('/\b' . $patterns['nameStopKeyWords'] . '\b/i', $matches['fullName'])) {
                $names = $this->parsePersonName($matches['fullName']);
                $properties['Login2'] = $names['lastname'];
            }
        }

        /**
         * @CardExample(accountId=3778504,cardUuid="348bb1d5-732d-495f-b493-2772b87bdb09",groupId="format5")
         */
        $pattern = '/'
            . 'Rewards.+(?:Club|CLUB)\b'                            // Rewards .. Club
            . '.*'
            . '\n(?<number>[\d ]{5,}\d)[ ]*\n'                      // Number
            . '[ ]*(?<fullName>[A-z][-\'A-z ]*[A-z])[ ]*(?:\n|$)'   // Name & Last Name
            . '/s';

        if (preg_match($pattern, $textFront, $matches)) {
            $properties['Login'] = str_replace(' ', '', $matches['number']);

            if (empty($properties['Login2']) && !preg_match('/\b' . $patterns['nameStopKeyWords'] . '\b/i', $matches['fullName'])) {
                $names = $this->parsePersonName($matches['fullName']);
                $properties['Login2'] = $names['lastname'];
            }
        }

        return $properties;
    }

    protected function parseFormat_6($textFront)
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        /**
         * @CardExample(accountId=2292105,cardUuid="da34689d-1298-4ea3-be70-211fc4c43e9f",groupId="format6")
         * @CardExample(accountId=3853258,cardUuid="945ea7ac-203f-408c-82ab-aedbf7e318e4",groupId="format6")
         * @CardExample(accountId=3841655,cardUuid="728443cd-624c-4b4d-bdcc-ffe718f581ef",groupId="format6")
         */
        $pattern = '/'
            . '\b([A-z][-\'A-z ]*[A-z])'	// Name & Last Name
            . '\s+([\d ]{5,}\d)'			// Number
            . '\s+[Cc]LUB\s*$'				// CLUB    |    cLUB
            . '/iu';
        $textFront = preg_replace('/(Member\s+\#\s*:\s*)/', '', $textFront);
        $textFront = preg_replace('/(Rewards\s*Club)/', '', $textFront);

        if (preg_match($pattern, $textFront, $matches)) {
            $properties['Login'] = str_replace(' ', '', $matches[2]);
            $names = $this->parsePersonName($matches[1]);
            $properties['Login2'] = $names['lastname'] ? $names['lastname'] : $names['firstname'];
        }

        return $properties;
    }

    protected function parseFormat_7($textFront)
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];
        $re = '/\s+(?<number>\d{3}[ ]+\d{3}[ ]+\d{3}|\d{9,})(?:\D|\b)\s+(?<fullName>[A-z][-\'A-z ]*[A-z])\s+ER SINCE \d{4}/';

        if (preg_match($re, $textFront, $m)) {
            $properties['Login'] = str_replace(' ', '', $m[1]);
            $names = $this->parsePersonName($m['fullName']);
            $properties['Login2'] = $names['lastname'] ? $names['lastname'] : $names['firstname'];
        }

        return $properties;
    }

    protected function parseFormat_99($textFull)
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        /**
         * @CardExample(accountId=4300265,cardUuid="cb2aed93-dccb-45e4-a3ec-975fb7882f58",groupId="format99")
         * @CardExample(accountId=4298706,cardUuid="afd92a59-fc96-49d2-885f-2f727871a075",groupId="format99")
         */
        if (preg_match('/Member[ ]*Number\s*:?\s*(?<number>\d{3}[ ]*\d{3}[ ]*\d{3}|\d{9,})(?:\D|\b)/i', $textFull, $matches)) {
            $properties['Login'] = str_replace(' ', '', $matches['number']);
        }

        return $properties;
    }

    protected function parseFormat_999($textFront)
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        $textFront = str_replace(['Spire', '①', 'pire', 'в‘'], ['', '', '', ''], $textFront);

        $patterns['nameStopKeyWords'] = '(' . implode('|', $this->stopKeyWords['name']) . ')';

        if (preg_match("/(?<fullName>[A-z][-\'’A-z\s]*[A-z])\s+(?<number>\d{3}[ ]+\d{3}[ ]+\d{3}|\d{9,})(?:\D|\b)/", $textFront, $matches)) {
            $properties['Login'] = str_replace(' ', '', $matches['number']);
            $matches['fullName'] = trim(preg_replace(['/\b' . $patterns['nameStopKeyWords'] . '\b/i', '/\s+/'], ['', ' '], $matches['fullName']));
            $names = $this->parsePersonName($matches['fullName']);
            $properties['Login2'] = $names['lastname'];
        } elseif (preg_match("/(?<fullName>[A-z][-\'’A-z\s]*[A-z])\s+\d{2}[ ]+\d{3}[ ]+\d{3}|\d{9,}(?:\D|\b)/", $textFront, $matches)) {
            $properties['Login'] = null;
            $matches['fullName'] = trim(preg_replace(['/\b' . $patterns['nameStopKeyWords'] . '\b/i', '/\s+/'], ['', ' '], $matches['fullName']));
            $names = $this->parsePersonName($matches['fullName']);
            $properties['Login2'] = $names['lastname'];
        }

        return $properties;
    }

    protected function detectCC_1(): bool
    {
        // example accounts: 3929500,3930131,3930202,3866094,3669336,3669336,3994093,3863165,4026299,3715765,3871379,3839768,3837062,3836733,3796844,3815911,3835932,3797080,3990242,3759745,3941169,3980070,3840545,2946669,1959575,2944754,2120268,3897757,4049244,2937433,3638476

        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $patterns = [
            'image' => '/(?:MasterCard|Chase)/i',
            'text'  => '/(?:\bMaster[ ]*Card\b|chase[ ]*\.[ ]*com|\bCHASE(?:o|c)?\b|wellsfargo[\.]com[\/a-z]+\s+Never\s+write\s+your\s+PIN\s+on\s+your\s+card|VALID\s*FROM\s*EXPIRES\s*END\s*\d*\s*mastercar|Club\#\s*masterc)/i',
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

    protected function hideCCNumber(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(5, 25, 90, 50)])
            ->setBack($rects);
    }

    protected function parsePersonName($string = ''): array
    {
        $result = ['prefix' => '', 'firstname' => '', 'middlename' => '', 'lastname' => ''];
        $nameParts = preg_split('/(?:[ ]+|\n)/', $string);

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

    private function re(string $re, string $str, int $i = 1): ?string
    {
        if (preg_match($re, $str, $m)) {
            return $m[$i] ?? null;
        }

        return null;
    }
}
