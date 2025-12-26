<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\iberia\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;
use AwardWallet\CardImageParser\ImageRecognitionResult;

class CardImageParserIberia implements CardImageParserInterface, CreditCardDetectorInterface
{
    /**
     * @var ImageRecognitionResult
     */
    private $front;

    /**
     * @var ImageRecognitionResult
     */
    private $back;

    private $devMode = 0;

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $result = [];

        if (null === ($this->front = $cardRecognitionResult->getFront())) {
            return [];
        }
        $result = $this->parseFormat_1();

        return $result;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // IB Plus No. / FQTV IB
        ];
    }

    /**
     * @Detector(version="1")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        // example accounts: 4254608

        $ccDetectionResult = new CreditCardDetectionResult();

        $this->front = $cardRecognitionResult->getFront();
        $this->back = $cardRecognitionResult->getBack();

        if (!$this->front && !$this->back) {
            return $ccDetectionResult;
        }

        $patterns = [
            'image' => '/(?:Visa)/i',
            'text'  => '/(?:\bVISA\b|\bChase\.com|\bchase com)/i',
        ];

        // FRONT

        if ($this->front) {
            $frontLogos = $this->front->getDOM(0)->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
            $frontLogoValues = array_values(array_filter($frontLogos));

            if (!empty($frontLogoValues[0])) {
                $this->hideCCNumber($ccDetectionResult);
            }

            $textFront = $this->front->getText(); // deviation: default

            if (preg_match($patterns['text'], $textFront)) {
                $this->hideCCNumber($ccDetectionResult);
            }
        }

        // BACK

        if ($this->back) {
            $backLogos = $this->back->getDOM(0)->findNodes('/img[@left > 50 or @top < 50]/@alt', null, $patterns['image']);
            $backLogoValues = array_values(array_filter($backLogos));

            if (!empty($backLogoValues[0])) {
                $this->hideCCNumber($ccDetectionResult);
            }

            $textBack = $this->back->getText(); // deviation: default

            if (preg_match($patterns['text'], $textBack)) {
                $this->hideCCNumber($ccDetectionResult);
            }
        }

        return $ccDetectionResult;
    }

    private function parseFormat_1(): array
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }
        $res = [];

        /**
         * @CardExample(accountId=3377028, cardUuid="82c6b7c6-2fa9-4c38-a4c9-a81ffb035d8e", groupId="format1")
         */
        if (null !== ($login = $this->front->getText(5)->findPreg('/(\d{7,8}|\d{2}\s+\d{6}|\d{4}\s+\d{4}|\d{6} \d{2})\b/'))) {
            $res['Login'] = preg_replace('/\s+/', '', $login);
        }

        return $res;
    }

    private function hideCCNumber(CreditCardDetectionResult &$ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(5, 30, 90, 40), new Rectangle(30, 70, 40, 25)])
            ->setBack($rects);
    }
}
