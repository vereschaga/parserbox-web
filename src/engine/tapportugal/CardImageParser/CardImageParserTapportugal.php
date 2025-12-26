<?php

namespace AwardWallet\Engine\tapportugal\CardImageParser;

use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserTapportugal implements CardImageParserInterface, CreditCardDetectorInterface
{
    private $frontSide;
    private $backSide;

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $result = [];

        $frontSide = $cardRecognitionResult->getFront();

        if (!$frontSide) {
            return [];
        }

        $textFull = ($frontSide ? $frontSide->getText() . "\n" : '');

        if (preg_match("/\n(?:TP|ΓΡ)\s*(?<number>[\d\s]+)/u", $textFull, $matches)) {
            $result['Login'] = str_replace(" ", "", $matches['number']);
        }

        return $result;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Member ID
        ];
    }

    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        // example accounts: 4242134,1649646

        $ccDetectionResult = new CreditCardDetectionResult();

        $this->frontSide = $cardRecognitionResult->getFront();
        $this->backSide = $cardRecognitionResult->getBack();

        if (!$this->frontSide && !$this->backSide) {
            return $ccDetectionResult;
        }

        $patterns = [
            'text' => '/(?:\bVISA\b|\bMasterCard\b)/i',
        ];

        // FRONT

        if ($this->frontSide) {
            $textFront = $this->frontSide->getText(); // deviation: default

            if (preg_match($patterns['text'], $textFront)) {
                $this->hideCCNumber($ccDetectionResult);
            }
        }

        // BACK

        if ($this->backSide) {
            $textBack = $this->backSide->getText(); // deviation: default

            if (preg_match($patterns['text'], $textBack)) {
                $this->hideCCNumber($ccDetectionResult);
            }
        }

        return $ccDetectionResult;
    }

    private function hideCCNumber(CreditCardDetectionResult &$ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(5, 30, 90, 40), new Rectangle(30, 70, 40, 25)])
            ->setBack($rects);
    }
}
