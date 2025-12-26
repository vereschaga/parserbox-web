<?php

namespace AwardWallet\Engine\shoppersdrugmart\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;

class CardImageParserShoppersdrugmart implements CardImageParserInterface
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
         * @CardExample(accountId=3871473, cardUuid="dd21af7a-9b45-4bc9-9d28-e32bf64853ee")
         * @CardExample(accountId=1350211, cardUuid="f947ca69-3e65-401b-a20c-e779b01c2938")
         * @CardExample(accountId=3959262, cardUuid="bdc0ea25-fb27-4873-a3a5-83bef89af8f4")
         * @CardExample(accountId=3837389, cardUuid="9cc7be95-4e23-4fcc-84c9-c5e99b3e21fa")
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

        // Number (back)

        $textBack = $this->backSide ? $this->backSide->getText() : ''; // deviation: default

        /**
         * @CardExample(accountId=3936766, cardUuid="da4f0c86-0def-4456-aef4-489af10658b6")
         */
        $in = ['O', 'o', 'U'];
        $out = '0';
        $textBackConverted = str_replace($in, $out, $textBack);

        $textBackConverted = str_replace(['.', ' ', '"'], '', $textBackConverted);

        $textBackConverted = preg_replace('/1-800-SH./i', '', $textBackConverted); // remove other numbers

        if (preg_match('/(?:\b|\D)(\d{9})(?:\b|\D)/', $textBackConverted, $matches)) {
            $properties['Login'] = $matches[1];

            return $properties;
        }

        // Number (front)

        $textFront = $this->frontSide ? $this->frontSide->getText() : ''; // deviation: default

        $in = ['O', 'o', 'b'];
        $out = ['0', '0', '6'];
        $textFrontConverted = str_replace($in, $out, $textFront);

        /**
         * @CardExample(accountId=3849496, cardUuid="f7d2be11-19fe-4a57-a378-7200a4111d18")
         */
        $textFrontConverted = str_replace(['.', ' ', '/'], '', $textFrontConverted);

        $textFrontConverted = preg_replace('/(?:\b|\D)6?0?320/', '', $textFrontConverted); // remove default prefixes (without last seven)

        if (preg_match('/\d(\d{9})(?:\b|\D)/', $textFrontConverted, $matches)) {
            $properties['Login'] = $matches[1];
        }

        return $properties;
    }
}
