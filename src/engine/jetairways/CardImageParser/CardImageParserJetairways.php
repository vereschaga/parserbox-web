<?php

namespace AwardWallet\Engine\jetairways\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;
use AwardWallet\CardImageParser\ImageRecognitionResult;

class CardImageParserJetairways implements CardImageParserInterface, CreditCardDetectorInterface
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

        if (!$this->frontSide) {
            return [];
        }

        /**
         * @CardExample(accountId=1424588, cardUuid="5e73c9c9-ab64-422b-87df-e9e4a4214096")
         * @CardExample(accountId=2173319, cardUuid="ee9f90e8-c12a-4052-9848-691b7c1a3c9c")
         * @CardExample(accountId=3874583, cardUuid="39ad3fbe-69d5-4384-a114-8fd4ef1c79b8")
         * @CardExample(accountId=3950191, cardUuid="6473880f-aa42-416a-a6f4-bfec0cd64b99")
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

    /**
     * @Detector(version="1")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        $cc = new CreditCardDetectionResult();
        $front = $cardRecognitionResult->getFront();
        $back = $cardRecognitionResult->getBack();
        $textFront = '';
        $textBack = '';

        if (!empty($front)) {
            $textFront = $front->getText();
        }

        if (!empty($back)) {
            $textBack = $back->getText();
        }

        if (preg_match('/ICICI Bank.*Me[nm](?:th|b)er S[i]?nc[ae]/is', $textFront) && preg_match('/[\.]icicibank\.com/', $textBack)) {
            $this->hideCCNumber($cc);
        }

        if (false !== stripos($textFront, 'CICI Bank') && preg_match('/\d{4} \d{4} \d{4} \d{4}/', $textFront) && false !== stripos($textBack, 'ICICI Bank')) {
            $this->hideCCNumber($cc);
        }

        return $cc;
    }

    protected function parseFormat_1()
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        /**
         * @CardExample(accountId=3974666, cardUuid="bb5604f3-4f26-45c3-ba9c-6127e503afa5")
         */
        $textFrontLeft = $this->frontSide->getDom(5)->getTextRectangle(0, 40, 0, 0); // deviation: 2-9
        $textFrontRight = $this->frontSide->getDom(5)->getTextRectangle(40, 0, 0, 0); // deviation: just as $textFrontLeft

        $textFront = $textFrontLeft . "\n" . $textFrontRight;

        // Number

        $textFrontConverted = preg_replace('/\bNO\b/i', '', $textFront);

        $textFrontConverted = str_replace(['.', ',', '-', ' '], '', $textFrontConverted);

        if (preg_match_all('/(?:\b|\D)(\d{9,10})(?:\b|\D)/', $textFrontConverted, $numberMatches)) {
            $properties['Login'] = $numberMatches[1][count($numberMatches[1]) - 1];
        }

        return $properties;
    }

    private function hideCCNumber(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(0, 20, 100, 60)])
            ->setBack($rects);
    }
}
