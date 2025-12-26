<?php

namespace AwardWallet\Engine\qmiles\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class MonthlyStatement2 extends \TAccountChecker
{
    public $mailFiles = "qmiles/statements/it-105657970.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@qr.qmiles.com') !== false;
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

        $email->setType('MonthlyStatement2');
        $root = $roots->length === 1 ? $roots->item(0) : null;

        $patterns['travellerName'] = '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        $name = $login = $number = $status = $balance = null;

        $name = $this->http->FindSingleNode("//text()[contains(normalize-space(),', welcome to your Privilege Club')]", null, true, "/^({$patterns['travellerName']})\s*, welcome to your Privilege Club/u");
        $st->addProperty('Name', preg_replace("/^(?:MRS|MR|MS|DR)[.\s]+(.+)$/i", '$1', $name));

        $login = $this->http->FindSingleNode("//text()[contains(normalize-space(),'This message was sent from Qatar Airways Privilege Club to')]/following::text()[normalize-space()][1]", null, true, "/^\S+@\S+$/");
        $st->setLogin($login);

        $number = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][normalize-space()='Membership Number'] ]/*[normalize-space()][2]", null, true, "/^\d{5,}$/");
        $st->setNumber($number);

        $status = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][normalize-space()='Membership Tier'] ]/*[normalize-space()][2]");
        $st->addProperty('MembershipLevel', $status);

        $balance = $this->http->FindSingleNode('.', $root, true, "/^\d[,.\'\d ]*$/");
        $st->setBalance($this->normalizeAmount($balance));

        if ($balance !== null) {
            $balanceDate = $this->http->FindSingleNode("following::text()[contains(normalize-space(),'Data as of')]", $root, true, "/Data as of\s+(.*\d.*)$/i");
            $st->parseBalanceDate($balanceDate);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        $roots = $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][normalize-space()='Qmiles balance'] ]/*[normalize-space()][2]");

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
}
