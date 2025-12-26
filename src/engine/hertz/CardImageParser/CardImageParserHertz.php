<?php

namespace AwardWallet\Engine\hertz\CardImageParser;

use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserHertz implements CardImageParserInterface, CreditCardDetectorInterface
{
    /**
     * @Detector(version="1")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        // example accounts: 2954074,4902134

        $ccDetectionResult = new CreditCardDetectionResult();

        $frontSide = $cardRecognitionResult->getFront();
        $backSide = $cardRecognitionResult->getBack();

        $patterns = [
            'image' => '/^(?:Visa|Mastercard)/i',
            'text'  => '/(?:\bVISA\b|BANK\s*OF\s*AMERICA|GAZPROM\s*BANK|Mastercard)/i',
        ];

        // FRONT

        if ($frontSide) {
            $frontLogos = $frontSide->getDOM()->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
            $frontLogoValues = array_values(array_filter($frontLogos));

            if (!empty($frontLogoValues[0])) {
                $this->hideCCNumber($ccDetectionResult);
            }

            $textFront = $frontSide->getText();

            if (preg_match($patterns['text'], $textFront)) {
                $this->hideCCNumber($ccDetectionResult);
            }
        }

        // BACK

        if ($backSide) {
            $backLogos = $backSide->getDOM()->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
            $backLogoValues = array_values(array_filter($backLogos));

            if (!empty($backLogoValues[0])) {
                $this->hideCCNumber($ccDetectionResult);
            }

            $textBack = $backSide->getText();

            if (preg_match($patterns['text'], $textBack)) {
                $this->hideCCNumber($ccDetectionResult);
            }
        }

        return $ccDetectionResult;
    }

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $result = [];

        if ($frontSide = $cardRecognitionResult->getFront()) {
            $frontText = $frontSide->getText();

            if (preg_match('/(?:^|\s)(\d{8})(?:\s|$)/m', $frontText, $m) or preg_match('/^\s*(\d{6} \d{2})\s*$/m', $frontText, $m)) {
                $result['Login'] = str_replace(" ", "", $m[1]);
            }
        }

        if (empty($result['Login']) && $backSide = $cardRecognitionResult->getBack()) {
            $backText = $backSide->getText();

            if ((strpos($backText, 'SIGNATURE') === false) && (strpos($backText, 'Card') === false) && preg_match('/(?:^|\s)(\d{8})(?:\s|$)/m', $backText, $m)) {
                $result['Login'] = $m[1];
            }
        }

        return $result;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // #1 Club Number
        ];
    }

    private function hideCCNumber(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(5, 30, 90, 50), new Rectangle(5, 80, 60, 10)])
            ->setBack($rects);
    }
}
