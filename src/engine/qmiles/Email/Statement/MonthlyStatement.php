<?php

namespace AwardWallet\Engine\qmiles\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class MonthlyStatement extends \TAccountChecker
{
    public $mailFiles = "qmiles/statements/it-105294397.eml, qmiles/statements/it-105295109.eml, qmiles/statements/it-661340539.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@qr.qmiles.com') !== false || stripos($from, 'email@qr.qatarairways.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".qatarairways.com/") or contains(@href,"qr.qatarairways.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"This message was sent from Qatar Airways") or contains(.,"@qr.qmiles.com")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();

        $email->setType('MonthlyStatement');
        $root = $roots->length === 1 ? $roots->item(0) : null;

        $patterns['travellerName'] = '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        $name = $status = $number = $balance = null;

        $name = $this->http->FindSingleNode("//text()[contains(normalize-space(),\", discover what's new this month\")]", null, true, "/^({$patterns['travellerName']})\s*, discover what's new this month/")
            ?? $this->http->FindSingleNode("//text()[contains(normalize-space(),\"to you and your family,\")]", null, true, "/to you and your family,\s*({$patterns['travellerName']})(?:\s*[,.:;!?]|$)/")
        ;
        $st->addProperty('Name', preg_replace("/^(?:MRS|MR|MS|DR)[.\s]+(.+)$/i", '$1', $name));

        $statusText = $this->htmlToText($this->http->FindHTMLByXpath('preceding-sibling::tr[normalize-space()][3]/*[normalize-space()][last()]', null, $root));

        if (preg_match("/^\s*(.{2,}?)\s+(\d{5,})\s*$/", $statusText, $m)) {
            $status = $m[1];
            $number = $m[2];
        }

        $st->addProperty('MembershipLevel', $status);
        $st->setNumber($number)->setLogin($number);

        $earnQpointsText = $this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][1]", $root);

        if (preg_match("/Earn\s+(\d[,.\'\d ]*?)\s+Qpoints to upgrade to/i", $earnQpointsText, $m)) {
            $st->addProperty('QpointsNextLevel', $m[1]);
        } elseif (preg_match("/Earn\s+(\d[,.\'\d ]*?)\s+Qpoints to retain/i", $earnQpointsText, $m)) {
            $st->addProperty('QpointsRetainLevel', $m[1]);
        }

        $balanceText = $this->htmlToText($this->http->FindHTMLByXpath('*[1]', null, $root));

        if (preg_match("/^\s*(\d[,.\'\d ]*)\s*(?:Qmiles balance|Avios balance)\s*$/i", $balanceText, $m)) {
            $balance = $this->normalizeAmount($m[1]);
            $validAt = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Valid at')]", null, true, "/Valid at\s+(.*\d.*)$/");
            $st->parseBalanceDate($validAt);
        }

        $qCreditsText = $this->htmlToText($this->http->FindHTMLByXpath('*[2]', null, $root));

        if (preg_match("/^\s*(\d[,.\'\d ]*?)\s*Qcredits\s*$/i", $qCreditsText, $m)) {
            $st->addProperty('Qcredits', $m[1]);
        }

        $qPointsText = $this->htmlToText($this->http->FindHTMLByXpath('*[3]', null, $root));

        if (preg_match("/^\s*(\d[,.\'\d ]*)\s*Qpoints balance\s*$/i", $qPointsText, $m)) {
            $st->addProperty('CurrentQpoints', $m[1]);
        }

        $st->setBalance($balance);

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        $roots = $this->http->XPath->query("//*[count(tr)=4]/tr[4][count(*)=3 and *[1]/descendant::text()[normalize-space()='Qmiles balance' or normalize-space()='Avios balance']]");

        return $roots;
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

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
