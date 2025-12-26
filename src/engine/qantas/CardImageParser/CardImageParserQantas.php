<?php

namespace AwardWallet\Engine\qantas\CardImageParser;

use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectionResult;
use AwardWallet\CardImageParser\CreditCardDetection\CreditCardDetectorInterface;
use AwardWallet\CardImageParser\CreditCardDetection\Rectangle;
use AwardWallet\CardImageParser\ImageRecognitionResult;

class CardImageParserQantas implements CardImageParserInterface, CreditCardDetectorInterface
{
    private $devMode = 0;

    private $namePrefixes = ['DOCTOR', 'MISS', 'MRS', 'MS', 'MR'];

    private $patterns = [
        'travellerName' => '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $result = [];

        $frontSide = $cardRecognitionResult->getFront();

        if (!$frontSide) {
            return [];
        }

        $textFrontBottom = $frontSide->getDOM(1)->getTextRectangle(0, 0, 50, 0);

        /**
         * @CardExample(accountId=4920116, cardUuid="995ff783-c09d-4764-a852-1c140b53abed", groupId="format1")
         * @CardExample(accountId=4656992, cardUuid="7308316c-49f7-4a95-a88a-41d85e54cbda", groupId="format1")
         */
        if (preg_match('/^MEMBER.*\sSINCE/im', $textFrontBottom, $m)
            && count($result = $this->parseFormat_1($frontSide))
        ) {
            return $result;
        }

        // Other Formats

        /**
         * @CardExample(accountId=4263265, cardUuid="aeb62b46-94ad-4225-9646-e78810e268c9", groupId="format999")
         */
        $result = $this->parseFormat_999($frontSide);

        return $result;
    }

    /**
     * @Detector(version="5")
     */
    public function detect(CardRecognitionResult $cardRecognitionResult): CreditCardDetectionResult
    {
        $ccDetectionResult = new CreditCardDetectionResult();

        /**
         * @CardExample(accountId=4178815, cardUuid="423f67e2-5632-4d11-a581-c2ea0f77b0ab", groupId="formatCC1")
         */
        if ($front = $cardRecognitionResult->getFront()) {
            $dom = $front->getDOM(5);
            $text = $front->getText();

            if (
                (0 !== count(array_filter($dom->findNodes('//img[@left > 70]', null, '/(express|paypal|visa|mastercard|credit\s+card)/i'))))
                || (null !== $text->findPreg('/(express|paypal|visa|mastercard|prepaid plotinum|prepaid\s+platinum)/ims'))
                || (null !== $dom->findSingleNode('//img[@left > 75 and @top > 30] | //img[@left > 50 and @top > 25]', null, '/(payment|Contactless\s+payment)/i') && $text->findPreg('/\b(master|prepaid\s+platinum)\b/i'))
                || (null !== $text->findPreg('/\bmasterco\b/i') && null !== $text->findPreg('/prepaid\s+platinum/i')) // masterco - its mastercard. trimmed
                || (null !== $text->findPreg('/\b\d{3}\b\n[a-z\s0-9]+\n\d{1,2}\/\d{1,2}/i'))
                || (null !== $text->findPreg('/\d{1,2}\/\d{2}\s+prepaid\s+\w+\s+\d{4}\s+\d{4}\s+\d{4}\s+\d{4}/i'))
            ) {
                $this->hideCcNumberFront($ccDetectionResult);
            }
        }

        /**
         * @CardExample(accountId=4177801, cardUuid="46e952db-a396-4353-a0cb-8a6342e6f3b6", groupId="formatCC1")
         */
        if ($back = $cardRecognitionResult->getBack()) {
            $dom = $back->getDOM(5);
            $text = $back->getText();

            if (
                (0 !== count(array_filter($dom->findNodes('//img[@left > 70]', null, '/(express|paypal|visa|mastercard|credit\s+card)/i'))))
                || (null !== $text->findPreg('/(express|paypal|visa|mastercard|prepaid plotinum|prepaid\s+platinum)/ims'))
                || (null !== $dom->findSingleNode('//img[@left > 75 and @top > 30] | //img[@left > 50 and @top > 25]', null, '/(payment|Contactless\s+payment)/i') && $text->findPreg('/\b(master|prepaid\s+platinum)\b/i'))
                || (null !== $text->findPreg('/\bmasterco\b/i') && null !== $text->findPreg('/prepaid\s+platinum/i')) // masterco - its mastercard. trimmed
                || (null !== $text->findPreg('/\b\d{3}\b\n[a-z\s0-9]+\n\d{1,2}\/\d{1,2}/i'))
                || (null !== $text->findPreg('/\b(?:QANTAS CASH|VALID THRU|paypass)\b/'))
                || (null !== $text->findPreg('/\d{1,2}\/\d{2}\s+prepaid\s+\w+\s+\d{4}\s+\d{4}\s+\d{4}\s+\d{4}/i'))
            ) {
                $this->hideCcNumberBack($ccDetectionResult);
            }
        }

        return $ccDetectionResult;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login', // Membership #
            'Login2', // Last name
        ];
    }

    private function parseFormat_1(ImageRecognitionResult $frontSide): array
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $result = [];

        $textFrontBottom = $frontSide->getDOM(6)->getTextRectangle(0, 0, 50, 0); // deviation: 3-9
        $textFrontBottom = preg_replace('/[ ]*[,.]+[ ]*/', ' ', $textFrontBottom);

        /*
            MISS V GARSIDE
            BRONZE MEMBER 190920 9346
            MEMBER SINCE AUG 2013
        */
        $pattern = '/^'
            . "(?<name>{$this->patterns['travellerName']})\n"
            . '\D*(?<number>(?: ?\d){7,10})\n'
            . '/u';

        if (preg_match($pattern, $textFrontBottom, $m)) {
            $result['Login'] = str_replace(' ', '', $m['number']);

            $names = $this->parsePersonName($m['name']);

            if (!empty($names['lastname'])) {
                $result['Login2'] = $names['lastname'];
            }
        }

        return $result;
    }

    private function parseFormat_999(ImageRecognitionResult $frontSide): array
    {
        if ($this->devMode) {
            echo ' --> ' . __FUNCTION__ . "\n";
        }

        $result = [];

        $xpathNamePrefix = $this->contains($this->namePrefixes);

        // FRONT

        if (null !== $frontSide) {
            $textFront = $frontSide->getText();

            if ($login = $textFront->findPreg('/(\b\d{10}\b|\b\d{9}\b|\b\d{7})/')) {
                if (stripos($textFront->getText(), 'Membership barcode') !== false) {
                    return [];
                }
                $name = $frontSide->getDOM(5)->findSingleNode("//div[contains(., '{$login}')]/preceding-sibling::div[{$xpathNamePrefix}]");

                if (empty($name)) {
                    $name = $frontSide->getDOM(5)->findSingleNode("//div[contains(., '{$login}')]/preceding-sibling::div[not(contains(., 'MEMBER')) and not(contains(., 'QANTAS')) and not(contains(., 'JOINED')) and not(contains(., 'barcode'))][1]");
                }
                $name = preg_replace('/[ ]*(?:onewor[lk]?[dc]?|EXPIRY|barcode| on)/', '', $name); // remove background keywords

                if (preg_match('/[A-Z\s\.]+\s+(\w+)/i', $name, $m) || preg_match('/(?:MR|MS|MISS)[\.]*(\w+)/i', $name, $m)) {
                    $result['Login2'] = $m[1];
                }

                $result['Login'] = preg_replace('/\s+/', '', $login);
            } elseif ($login = $textFront->findPreg('/\b(\d{2}\s+\d{4}\s+\d{4})\b/')) {
                $name = $frontSide->getDOM(5)->findSingleNode("//div[contains(., '{$login}')]/preceding-sibling::div[1]");

                if (preg_match('/\w+/', $name)) {
                    $nameParts = preg_split('/\s+/', $name);
                    $result['Login2'] = $nameParts[count($nameParts) - 1];
                }
                $result['Login'] = $login;
            } else {
                $name = $frontSide->getDOM(5)->findSingleNode("//div[{$xpathNamePrefix}][1]", null, '/[\.\sa-z]+\s*\b(\w+)\b/i');

                if (preg_match('/\w+/', $name)) {
                    $result['Login2'] = $name;
                }
            }
        }

        return $result;
    }

    private function hideCcNumberFront(CreditCardDetectionResult &$ccDetectionResult)
    {
        return $ccDetectionResult->setFront([new Rectangle(0, 30, 100, 40)]);
    }

    private function hideCcNumberBack(CreditCardDetectionResult &$ccDetectionResult)
    {
        return $ccDetectionResult->setBack([new Rectangle(0, 10, 100, 60)]);
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function parsePersonName(?string $string): array
    {
        $result = ['prefix' => null, 'firstname' => null, 'middlename' => null, 'lastname' => null];
        $nameParts = preg_split('/[ ]+/', trim($string));

        if (count($nameParts) > 1) {
            $namePartFirst = mb_strtolower($nameParts[0]);
            $namePartLast = mb_strtolower($nameParts[count($nameParts) - 1]);

            foreach ($this->namePrefixes as $prefix) {
                $prefix = mb_strtolower(str_replace('.', '', $prefix));

                if ($namePartFirst === $prefix) {
                    $result['prefix'] = array_shift($nameParts);
                } elseif ($namePartLast === $prefix) {
                    $result['prefix'] = array_pop($nameParts);
                }
            }
        }

        if (count($nameParts) === 1) {
            $result['firstname'] = $nameParts[0];
        } elseif (count($nameParts) === 2) {
            $result['firstname'] = $nameParts[0];
            $result['lastname'] = $nameParts[1];
        } elseif (count($nameParts) === 3) {
            $result['firstname'] = $nameParts[0];
            $result['middlename'] = $nameParts[1];
            $result['lastname'] = $nameParts[2];
        } elseif (count($nameParts) > 3) {
            $result['firstname'] = $nameParts[0];
            $result['lastname'] = $nameParts[count($nameParts) - 1];
        }

        return $result;
    }
}
