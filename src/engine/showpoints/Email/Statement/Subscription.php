<?php

namespace AwardWallet\Engine\showpoints\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Subscription extends \TAccountChecker
{
    public $mailFiles = "showpoints/statements/it-65427942.eml, showpoints/statements/it-65545706.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@audiencerewards.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//node()[contains(normalize-space(),"This email was sent by: Audience Rewards") or contains(.,"@audiencerewards.com")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot1()->length > 0 || $this->findRoot2()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $number = $balance = null;

        // it-65545706.eml
        $roots1 = $this->findRoot1();

        if ($roots1->length > 0) {
            $this->logger->debug('Found root-1.');
            $root1 = $roots1->item(0);
            $headerText = implode(' ', $this->http->FindNodes('descendant::text()[normalize-space()]', $root1));

            if (preg_match("/Hello,\s*(?<name>[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])?\s*\(\s*Account No\.[:\s]*(?<number>[-A-Z\d]{5,})\s*\)\.\s*You currently have\s*(?<balance>\d[,.\'\d ]*?)\s*ShowPoints/iu", $headerText, $m)) {
                // Hello, Patrick Larson (Account No. 601409822). You currently have 0 ShowPoints!
                if (!empty($m['name'])) {
                    $name = $m['name'];
                }
                $number = $m['number'];
                $balance = $m['balance'];
            }
        }

        // it-65427942.eml
        $roots2 = $this->findRoot2();

        if ($roots2->length === 1) {
            $this->logger->debug('Found root-2.');
            $root1 = $roots2->item(0);
            $headerText = implode(' ', $this->http->FindNodes('descendant::text()[normalize-space()]', $root1));

            if (preg_match("/MY INFORMATION\s*\|\s*(?<name>[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])?\s*\|\s*Account #[:\s]+(?<number>[-A-Z\d]{5,})\s*\|\s*Point Balance[:\s]+(?<balance>\d[,.\'\d ]*)\b/iu", $headerText, $m)) {
                // MY INFORMATION | Patrick Larson | Account #: 601407822 | Point Balance: 0
                if (!empty($m['name'])) {
                    $name = $m['name'];
                }
                $number = $m['number'];
                $balance = $m['balance'];
            }
        }

        if ($name !== null) {
            $st->addProperty('Name', $name);
        }

        $st->setNumber($number)
            ->setLogin($number);

        $st->setBalance($this->normalizeAmount($balance));

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot1(): \DOMNodeList
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(),'Account No.') and contains(normalize-space(),'You currently have')]/ancestor::*[self::div or self::tr or self::p][1]");
    }

    private function findRoot2(): \DOMNodeList
    {
        return $this->http->XPath->query("//p[contains(normalize-space(),'Account #') and contains(normalize-space(),'Point Balance')]");
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }
}
