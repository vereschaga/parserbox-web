<?php

namespace AwardWallet\Engine\hawaiian\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserHawaiian implements CardImageParserInterface, CreditCardDetectorInterface
{
    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $result = [];

        if ($front = $cardRecognitionResult->getFront()) {
            $cardText = $front->getText();

            $noSpaces = preg_replace("# +#", '', $cardText->getText());

            if (preg_match('#\D(\d{9})[^\d\/]#', $noSpaces, $m)) {
                $result['Login'] = str_replace(" ", "", $m[1]);
            }
        }

        if (!isset($result['Login']) && ($back = $cardRecognitionResult->getBack())) {
            $cardText = $back->getText();

            $noSpaces = preg_replace("# +#", '', $cardText->getText());

            if (preg_match('#\D(\d{9})[^\d\/]#', $noSpaces, $m)) {
                $result['Login'] = $m[1];
            }
        }

        return $result;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', //Number
        ];
    }

    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        $ccDetectionResult = new CreditCardDetectionResult();
        /**
         * @CardExample(accountId=4018413 )
         */
        $front = $cardRecognitionResult->getFront() ? $cardRecognitionResult->getFront()->getText() : '';
        $back = $cardRecognitionResult->getBack() ? $cardRecognitionResult->getBack()->getText() : '';

        if (!empty($front) && preg_match('/(mastercard|VALID\s+THRU)/ims', $front)) {
            $this->hideCCNumber($ccDetectionResult);
        }

        if (!empty($back) && preg_match('/(Credit\s*Card|by Barclays|Mastercard)/ims', $back)) {
            $this->hideCCNumber($ccDetectionResult);
        }

        return $ccDetectionResult;
    }

    protected function hideCCNumber(CreditCardDetectionResult &$ccDetectionResult)
    {
        return $ccDetectionResult
            ->setFront([new Rectangle(0, 30, 100, 40)])
            ->setBack([new Rectangle(0, 30, 100, 40)]);
    }
}
