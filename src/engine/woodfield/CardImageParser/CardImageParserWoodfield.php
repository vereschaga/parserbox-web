<?php

namespace AwardWallet\Engine\woodfield\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;

class CardImageParserWoodfield implements CardImageParserInterface
{
    private $devMode = 0;

    private $namePrefixes = ['Miss', 'Mr.', 'Mrs.', 'Ms.', 'Dr.'];

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $result = [];

        $frontSide = $cardRecognitionResult->getFront();

        if (!$frontSide) {
            return [];
        }

        /**
         * @CardExample(accountId=1149400, cardUuid="ebf40656-e5aa-418b-8800-17e3687d8417", groupId="format1")
         */
        $textFront = $frontSide ? $frontSide->getText() : ''; // deviation: default    |    3-4

        if (preg_match('/^MEMBER[ ]*NUMBER[ ]*:.*$\s+^CURRENT[ ]*MEMBER[ ]*LEVEL[ ]*:/im', $textFront)) {
            if ($result = $this->parseFormat_1($frontSide)) {
                return $result;
            }
        }

        /**
         * @CardExample(accountId=4478868, cardUuid="50a306c9-cbfc-410e-9282-5bb8794cfa3c", groupId="format2")
         */
        $textFront = $frontSide ? $frontSide->getText() : ''; // deviation: default

        if (preg_match('/\bMember[ ]*Number[ ]*:/i', $textFront)) {
            if ($result = $this->parseFormat_2($textFront)) {
                return $result;
            }
        }

        $textFront = $frontSide ? $frontSide->getDom(3)->getTextRectangle(0, 0, 60, 0) : ''; // deviation: 1-6
        $textFrontConverted = str_replace(['.', ' '], '', $textFront);

        if (preg_match('/(?:\b|\D)[A-z][OC\d ]{7,}\b/i', $textFrontConverted)) {
            if ($result = $this->parseFormat_3($textFront)) {
                return $result;
            }
        }

        // etc.

        return $result;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Returns Account
            'Login2', // Last Name
        ];
    }

    private function parseFormat_1($frontSide)
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $result = [];

        $textFront = $frontSide ? $frontSide->getText(3) : ''; // deviation: default    |    3-4

        $textFrontConverted = str_replace('.', '', $textFront);

        // Last Name & Number

        $pattern = '/'
            . '\b(?<fullName>[A-z][-\'A-z ]*[A-z])[ ]*$'                                   // Name & Last Name
            . '\s+^[ ]*MEMBER[ ]*NUMBER[ ]*:[ ]*(?<number1>[A-z])(?<number2>[OC\d ]{7,})$' // MEMBER NUMBER: P60163739
            . '\s+^[ ]*CURRENT[ ]*MEMBER[ ]*LEVEL'                                         // CURRENT MEMBER LEVEL: Silver
            . '/im';

        if (preg_match($pattern, $textFrontConverted, $matches)) {
            $names = $this->parsePersonName($matches['fullName']);
            $result['Login2'] = $names['lastname'];
            $result['Login'] = strtoupper($matches['number1']) . str_ireplace(['O', 'C', ' '], ['0', '0', ''], $matches['number2']);
        }

        return $result;
    }

    private function parseFormat_2($textFront)
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $result = [];

        // Number

        $textFrontConverted = str_replace(['.', ' '], '', $textFront);

        // MemberNumber:\nP774108243    |    W774108243\nMemberNumber:
        if (
            preg_match('/^MemberNumber:\s*(?<number1>[A-z])(?<number2>[OC\d]{7,})$/im', $textFrontConverted, $matches)
            || preg_match('/^(?<number1>[A-z])(?<number2>[OC\d]{7,})\s*MemberNumber:$/im', $textFrontConverted, $matches)
        ) {
            $result['Login'] = strtoupper($matches['number1']) . str_ireplace(['O', 'C'], '0', $matches['number2']);
        }

        return $result;
    }

    private function parseFormat_3($textFront)
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $result = [];

        $textFrontConverted = str_replace('.', '', $textFront);
        $textFrontConverted = str_ireplace(['Member', 'Number', 'valid', 'thru'], '', $textFrontConverted);

        // Last Name & Number

        if (preg_match('/^(?<fullName>[A-z][-\'A-z ]*[A-z])\b[ ]+\b(?<number1>[A-z])(?<number2>[OC\d ]{7,})$/im', $textFrontConverted, $matches)) {
            /**
             * @CardExample(accountId=3733816, cardUuid="fd163ee4-6e37-4013-8e86-5eb4e3f2269a", groupId="format3")
             * @CardExample(accountId=3869178, cardUuid="247755f7-a47a-47f7-9a5b-bb53821d3a2b", groupId="format3")
             */
            $names = $this->parsePersonName($matches['fullName']);
            $result['Login2'] = $names['lastname'];
            $result['Login'] = strtoupper($matches['number1']) . str_ireplace(['O', 'C', ' '], ['0', '0', ''], $matches['number2']);
        } elseif (preg_match('/^(?<fullName>[A-z][-\'A-z ]*[A-z])\b.*$\s+^(?<number1>[A-z])(?<number2>[OC\d ]{7,})$/im', $textFrontConverted, $matches)) {
            /**
             * @CardExample(accountId=3947853, cardUuid="6f09767d-3872-415d-a8e7-4355e4fc1388", groupId="format3")
             */
            $names = $this->parsePersonName($matches['fullName']);
            $result['Login2'] = $names['lastname'];
            $result['Login'] = strtoupper($matches['number1']) . str_ireplace(['O', 'C', ' '], ['0', '0', ''], $matches['number2']);
        } elseif (preg_match('/\b(?<number1>[A-z])(?<number2>[OC\d]{7,})$/i', str_replace(' ', '', $textFrontConverted), $matches)) {
            /**
             * @CardExample(accountId=4288660, cardUuid="6d4d5320-5f6a-4611-911f-99361fdef0ed", groupId="format3")
             */
            $result['Login'] = strtoupper($matches['number1']) . str_ireplace(['O', 'C'], ['0', '0'], $matches['number2']);
        }

        return $result;
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
