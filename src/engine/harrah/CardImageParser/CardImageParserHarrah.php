<?php

namespace AwardWallet\Engine\harrah\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserHarrah implements CardImageParserInterface, CreditCardDetectorInterface
{
    /**
     * @Detector(version="1")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        // example accounts: 4925255,3307181,5027128,4839364

        $ccDetectionResult = new CreditCardDetectionResult();

        $frontSide = $cardRecognitionResult->getFront();
        $backSide = $cardRecognitionResult->getBack();

        $patterns = [
            'image' => '/^(?:Visa)/i',
            'text'  => '/(?:\bVISA\b|Visa Concierge:|\bthis[\s\S]+credit\s*card\b)/i',
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

        $frontSide = $cardRecognitionResult->getFront();

        $textFront = $frontSide ? $frontSide->getText() : '';

        /**
         * @CardExample(accountId=4290392, cardUuid="abb64f26-bbc9-44a7-87d7-f7522b234240", groupId="format1BadSimbols")
         * @CardExample(accountId=4459013, cardUuid="fc71b2fb-51a4-4ea1-b55a-dd8b382283b5", groupId="format1BadSimbols")
         */
        $textFrontConverted = str_replace(['U', '工'], ['0', '1'], $textFront);

        /**
         * @CardExample(accountId=3596504, cardUuid="c4319e9e-e515-425f-b910-37047d7d2b43", groupId="format1BadSpaces")
         */
        $textFrontNoSpaces = str_replace(' ', '', $textFrontConverted);

        /**
         * @CardExample(accountId=3954007, cardUuid="48c3a081-2f34-4a9c-8f55-dd751b942f9c", groupId="format1BadNewlines")
         */
        $textFrontSingleline = str_replace("\n", '', $textFrontNoSpaces);

        /**
         * @CardExample(accountId=4940423, cardUuid="e141aa29-6c20-4fb8-940f-43a6a5e2db43", groupId="format1")
         * @CardExample(accountId=4929880, cardUuid="94550a9f-e01a-4cca-8145-42e2cdfaa36d", groupId="format1")
         */
        $pattern = '/'
            . '(?:\b|\D)(?<number>\d{11})(?:\b|\D)' // Number
            . '/';

        if (preg_match($pattern, $textFrontConverted, $matches)
            || preg_match($pattern, $textFrontNoSpaces, $matches)
            || preg_match($pattern, $textFrontSingleline, $matches)
        ) {
            $result['Login'] = $matches['number'];
        }

        return $result;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Caesars Rewards #
        ];
    }

    private function hideCCNumber(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(5, 30, 90, 50)])
            ->setBack($rects);
    }
}
