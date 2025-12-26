<?php

namespace AwardWallet\Engine\marriott\CardImageParser;

use AwardWallet\CardImageParser\Annotation\CardExample;
use AwardWallet\CardImageParser\Annotation\Detector;
use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;

class CardImageParserMarriott implements CardImageParserInterface, CreditCardDetectorInterface
{
    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $result = [];

        // FRONT

        if (($front = $cardRecognitionResult->getFront())) {
            $textFront = $front->getText();

            /**
             * @CardExample(accountId=3942209, cardUuid="5b56390a-edb6-4da1-9fb1-17067c25329e", groupId="marriott9front")
             * @CardExample(accountId=3684459, cardUuid="b8513992-0f5e-492a-af2e-05ab8f27989c", groupId="marriott9front")
             * @CardExample(accountId=4931931, cardUuid="29a8d041-045e-45e3-874a-89113548b49c", groupId="marriott9front")
             * @CardExample(accountId=4932607, cardUuid="af2062de-8e4e-4e95-a324-108f1a74a9bc", groupId="marriott9front")
             * @CardExample(accountId=3927045, cardUuid="770453e3-073d-49d2-b650-802c50b535ad", groupId="spg11")
             */
            $textFrontConverted = str_replace(['l', '/', 'a'], ['1', '1', '4'], $textFront);

            if (preg_match('/^(?<number>\d{3} \d{3} \d{3})$/m', $textFrontConverted, $m)
                || preg_match('/^(?<number>\d{3}\d{3}\d{3})$/m', $textFrontConverted, $m)
            ) {
                // only ideals: 386 361 395    |    386361395
                $result['Login'] = str_replace(' ', '', $m['number']);
            } elseif (preg_match('/\b(?<number>\d{3}[,. ]*\d{3}[,. ]*\d{3}|\d{11})\b/', $textFrontConverted, $m)) {
                $result['Login'] = str_replace([' ', '.', ','], '', $m['number']);
            }
        }

        // BACK

        if (($back = $cardRecognitionResult->getBack())) {
            $textBack = $back->getText();

            /**
             * @CardExample(accountId=4931310, cardUuid="f2a66f01-b525-420f-82d5-3e7e85ef7ad1", groupId="marriott9back")
             * @CardExample(accountId=4920371, cardUuid="b1d3973a-c1de-4d9c-af4e-15a987105d62", groupId="marriott9back")
             * @CardExample(accountId=4955481, cardUuid="46e164c8-6d49-4198-8bf5-d2df6543144e", groupId="marriott9back")
             */
            if (preg_match('/^(?<number>\d{9})$/m', $textBack, $m)) {
                // only ideals: 386361395
                $result['Login'] = $m['number'];
            }
        }

        return $result;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Email or Rewards No.
        ];
    }

    /**
     * @Detector(version="5")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        // example accounts: 3775377,4459453,4500788,4501910,4633923,4645228

        $ccDetectionResult = new CreditCardDetectionResult();

        $patterns = [
            'image' => '/(?:^Visa|express|paypal|mastercard)/i',
            //            'text' => '/(?:)/i',
        ];

        // FRONT

        if (($front = $cardRecognitionResult->getFront())) {
            $textFront = $front->getText(5);
            $domFront = $front->getDOM(5);

            if (
                (null !== $domFront->findSingleNode('//img[@left > 40 and @top > 40]', null, $patterns['image']))
                || (null !== $textFront->findPreg('/(america|express|paypal|\bVISA\b|\bCHASE\b|(?-i)\bAMEX\b(?i)|mastercard|VALID THRU VIS|\d{4} \d{4} \d{4} \d{4}\s+(?:valid|vald thru|[t]?a[d]?\s+[o]?\d{1,2}\/\d{2}|\d{4}\s+valid\s+thru)|Signature|'
                        . 'Valid Thru Member Since|(?:[\db]{4,5} ){2,4}\s*\n\s*Valid Thru|Valid \d{1,2}\/\d{1,2} Thru Member Since\nAMERIC EXPF)/i'))
                || preg_match('/(?:\n\d{4} \d{4} \d{4} \d{4}\nVALID|VISA?\s+Signa(?:ture)?\s*$)/i', $front->getText())
            ) {
                $this->hideCCNumber($ccDetectionResult);
            }
        }

        // BACK

        if (($back = $cardRecognitionResult->getBack())) {
            $textBack = $back->getText(5);

            if (
                (null !== $textBack->findPreg('/(Valid without Authorized Signature|1-800-338-5960|americanexpress\.com|\bchase\.com\b|Nordstrom Credit Card|A?MERICAN\s*EXPRESS?|\s+open.com.*\s*\s+OPEN(?:\s+|$))/ims'))
                || (preg_match('/[JL][.]?P?[.]?Morgan\s+(Use[ ]*of[ ]*this[ ]*card|.+Infinite\s*$)/is', $textBack))
                || preg_match('/\d{4} \d{6} \d{5} \d{3} AME[FX]\nCardmember Signature/', $textBack)
            ) {
                $this->hideCCNumber($ccDetectionResult);
            }
        }

        return $ccDetectionResult;
    }

    private function hideCCNumber(CreditCardDetectionResult $ccDetectionResult)
    {
        $ccDetectionResult
            ->setFront($rects = [new Rectangle(5, 30, 90, 50)])
            ->setBack($rects);
    }
}
