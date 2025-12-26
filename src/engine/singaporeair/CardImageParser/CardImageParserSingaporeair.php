<?php

namespace AwardWallet\Engine\singaporeair\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserSingaporeair implements CardImageParserInterface, CreditCardDetectorInterface
{
    protected $ccDetectionResult;

    protected $frontSide;
    protected $backSide;

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

        if ($properties = $this->parseFormat_1()) {
            return $properties;
        }

        return $properties;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // KrisFlyer No.
        ];
    }

    /**
     * @Detector(version="2018-08-28")
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
        }

        return $this->ccDetectionResult;
    }

    protected function parseFormat_1()
    {
        $properties = [];

        $textFront = $this->frontSide ? $this->normalizeText($this->frontSide->getText()) : '';

        /**
         * @CardExample(accountId=3528249, cardUuid="1c107206-1f69-434f-997d-8268c8bb50d6")
         * @CardExample(accountId=2042050, cardUuid="054411b1-6c2a-4541-8562-5df161d1c43a")
         * @CardExample(accountId=960375, cardUuid="19e71271-5fcc-4f06-be5d-4489d0dbded8")
         */
        $pattern = '/'
            . '(?:^|\D)(?<number>\d[\d ]{8,10}\d)(?:$|\D)'	// Number
            . '/m';
        preg_match_all($pattern, $textFront, $numberMatches);

        foreach ($numberMatches['number'] as $number) {
            $number = str_replace(' ', '', $number);
            /**
             * @CardExample(accountId=3716385, cardUuid="3ee42e2e-a898-4d1d-910a-568b198ac022")
             */
            if (strlen($number) === 10) {
                $properties['Login'] = $number;

                break;
            }
        }

        return $properties;
    }

    protected function normalizeText($string = '')
    {
        $string = str_replace('.', '', $string);
        $string = str_replace("\n", ' ', $string);

        /**
         * @CardExample(accountId=3724831, cardUuid="1f6d3031-31a5-40a5-8e09-3eb6efb9ed47")
         */
        $string = preg_replace('/\([\d ]+\)[ ]*[\d ]{5,}/s', '', $string); // remove phone numbers

        /**
         * @CardExample(accountId=3513540, cardUuid="35e4d7d4-7782-4d70-a14d-b7a9fb4d8929")
         * @CardExample(accountId=3623084, cardUuid="ee69f46a-0beb-44f7-af68-c59dc2cc6f39")
         */
        $monthNames = [
            "january"   => 0, "jan" => 0,
            "february"  => 1, "feb" => 1,
            "march"     => 2, "mar" => 2,
            "april"     => 3, "apr" => 3,
            "may"       => 4, "mai" => 4,
            "june"      => 5, "jun" => 5,
            "july"      => 6, "jul" => 6,
            "august"    => 7, "aug" => 7,
            "september" => 8, "sep" => 8,
            "october"   => 9, "oct" => 9,
            "november"  => 10, "nov" => 10,
            "december"  => 11, "dec" => 11,
        ];
        $string = preg_replace('/\b(' . implode('|', array_keys($monthNames)) . ')[ ]+20\d{2}\b/i', '', $string); // remove month & year

        $string = str_replace([')', '('], '', $string);
        $string = preg_replace('/.*?(\d.*)/s', '$1', $string);

        return $string;
    }

    protected function detectCC_1(): bool
    {
        // example accounts: 4158422

        //		$frontSide = $this->frontSide ? $this->frontSide->getText() : '';
//
        //		if ( !== false)
        //			return true;

        $textBack = $this->backSide ? $this->backSide->getText() : '';

        if (stripos($textBack, 'of PT Bank Central Asia') !== false || preg_match('#american\s*express#i', $textBack)) {
            return true;
        }

        return false;
    }

    protected function hideCCNumber_1(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(0, 50, 100, 45), new Rectangle(40, 0, 40, 100)])
            ->setBack($rects);
    }
}
