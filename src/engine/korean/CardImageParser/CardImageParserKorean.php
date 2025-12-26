<?php

namespace AwardWallet\Engine\korean\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserKorean implements CardImageParserInterface, CreditCardDetectorInterface
{
    protected $devMode = 0;

    /**
     * @Detector(version="2")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        $cc = new CreditCardDetectionResult();

        $textFront = $cardRecognitionResult->getFront() ? $cardRecognitionResult->getFront()->getText() : '';

        if (preg_match('/(?:\bVISA\b|Valid\s+Thru\s+memb[ae]r\s+since\s+american\s+express)/i', $textFront)) {
            $this->hideCCNumber($cc);
        }

        return $cc;
    }

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        $frontSide = $cardRecognitionResult->getFront();

        if (!$frontSide) {
            return [];
        }

        if ($properties = $this->parseFormat_1($frontSide)) {
            return $properties;
        }

        return $properties;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Account Number
        ];
    }

    protected function parseFormat_1($frontSide)
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $properties = [];

        $in = ['O', 'o', 'U', 'l', 'b'];
        $out = ['0', '0', '0', '1', '6'];

        // Number (Type 1)

        /**
         * @CardExample(accountId=3928579, cardUuid="eb4921b7-5498-4e67-9b33-48253ada38c4")
         * @CardExample(accountId=3283291, cardUuid="e2bf1f8f-4a1e-4be5-8340-1f59ad176765")
         * @CardExample(accountId=2783368, cardUuid="11ae9f78-3497-4515-91ef-fa443419b8ff")
         */
        $textFront = $frontSide ? $frontSide->getText(5) : ''; // deviation: 2-9

        $textFrontConverted = str_replace(['.', ' '], '', $textFront);

        if (preg_match('/\b(B[A-Zu])([' . implode('', $in) . '\d]{8})/', $textFrontConverted, $matches)) {
            $properties['Login'] = strtoupper($matches[1]) . str_replace($in, $out, $matches[2]);

            return $properties;
        }

        // Number (Type 2)

        /**
         * @CardExample(accountId=1008592, cardUuid="ed14c188-9fed-43a2-8773-d3fbc239ba63")
         */
        $textFront = $frontSide ? $frontSide->getText() : ''; // deviation: default

        $textFrontConverted = str_replace(['.', ' '], '', $textFront);

        $textFrontConverted = str_replace($in, $out, $textFrontConverted);

        if (preg_match('/(?:\b|\D)(\d{12})(?:\b|\D)/', $textFrontConverted, $matches)) {
            $properties['Login'] = $matches[1];
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
