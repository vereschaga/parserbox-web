<?php

namespace AwardWallet\Engine\perfectdrive\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;

class CardImageParserPerfectdrive implements CardImageParserInterface
{
    protected $devMode = 0;

    protected $frontSide;
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
         * @CardExample(accountId=3746773, cardUuid="856c9e36-c82c-4ace-97a4-245215cb18d0")
         * @CardExample(accountId=3923029, cardUuid="779a5a0c-d178-48df-a5d7-403bbaa6ce3d")
         * @CardExample(accountId=3887680, cardUuid="9b8b56ad-bed5-4087-99f2-0d1809fb7184")
         * @CardExample(accountId=3867932, cardUuid="0c352b23-f1a7-4725-83a8-63504eb1089c")
         * @CardExample(accountId=3874679, cardUuid="cc31c1f4-3859-48a9-9964-2cafa744a421")
         */
        if ($properties = $this->parseFormat_1()) {
            return $properties;
        }

        return $properties;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login',
        ];
    }

    protected function parseFormat_1()
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        // Number

        // FRONT

        $textFront = $this->frontSide ? $this->frontSide->getDOM(2)->getTextRectangle(0, 0, 25, 30) : ''; // deviation: 1-4

        $textFrontConverted = str_replace(['.', ' '], '', $textFront);

        $textFrontConverted = str_ireplace(['Fastbreak', 'RapidRez', 'Number', 'Budget', 'Dashboard'], ' ', $textFrontConverted);

        preg_match_all('/(?:\b|[^A-Z\d])([A-Z\d]{6})\b/', $textFrontConverted, $numberMatches);

        foreach ($numberMatches[1] as $number) {
            if (preg_match('/[A-Z]/', $number) && preg_match('/\d/', $number)) {
                $properties['Login'] = $number;

                return $properties;
            }
        }
        $textFront = $this->frontSide ? $this->frontSide->getText() : '';

        if (preg_match("/(?:RapidRez Number|Fastbreak\s+BCN|BCN#|RapidRez ID #|\nBoN|\nBCN)\s*([A-Z\d]{6})\s*(?:\n|$)/", $textFront, $m)) {
            $properties['Login'] = $m[1];

            return $properties;
        }

        // BACK

        if (empty($properties['Login'])) {
            $textBack = $this->backSide ? $this->backSide->getText() : ''; // deviation: default

            /**
             * @CardExample(accountId=3995669, cardUuid="1c41aa97-ee37-405a-b760-aeb7537309d2")
             */
            if (preg_match('/RapidRez\s*Number\s*([A-Z\d]{6})\b/', $textBack, $matches)) {
                if (preg_match('/[A-Z]/', $matches[1]) && preg_match('/\d/', $matches[1])) {
                    $properties['Login'] = $matches[1];

                    return $properties;
                }
            }
        }

        return $properties;
    }
}
