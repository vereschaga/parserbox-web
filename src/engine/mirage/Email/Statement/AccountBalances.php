<?php

namespace AwardWallet\Engine\mirage\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class AccountBalances extends \TAccountChecker
{
    public $mailFiles = "mirage/statements/it-138558720.eml, mirage/statements/it-138970057.eml, mirage/statements/it-62622826.eml, mirage/statements/it-63942256.eml, mirage/statements/it-64046144.eml, mirage/statements/it-64109766.eml, mirage/statements/it-93798048.eml, mirage/statements/it-93928852.eml";

    public function detectEmailFromProvider($from)
    {
        return preg_match('/(?:mlife|mgmrewards)@(ee|em)\.mgmresorts\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return self::detectEmailFromProvider($headers['from']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".mgmresorts.com/") or contains(@href,"ee.mgmresorts.com") or contains(@href,"em.mgmresorts.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"MGM Resorts International®. All rights reserved")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1 || $this->findRoot2()->length === 1
            || $this->http->XPath->query('//text()[starts-with(normalize-space(),"Account balances as of")]')->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $email->setType('AccountBalances');

        $name = $accountNumber = $tierLevel = $points = $expressComps = $HGSPoints = $asOf = null;

        $patterns['separator'] = '[-–]';

        // it-63942256.eml
        $roots = $this->findRoot();

        if ($roots->length === 1
            && (preg_match("/^(?<name>[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s+{$patterns['separator']}\s+(?<number>[A-Z\d]{5,})$/u", $this->http->FindSingleNode('*[1]', $roots->item(0)), $m)
                || preg_match("/^(?<name>[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s+{$patterns['separator']}$/u", $this->http->FindSingleNode('*[1]', $roots->item(0)), $m)
            )
        ) {
            // Todd Sullivan - 56489095
            $name = $m['name'];

            if (!empty($m['number'])) {
                $accountNumber = $m['number'];
            }
        }

        // it-64046144.eml, it-64109766.eml
        $roots = $this->findRoot2();

        if ($roots->length === 1
            && (preg_match("/^(?<name>[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s+{$patterns['separator']}\s+(?<number>[A-Z\d]{5,})\s*\|\s*(?i)My Account$/u", $this->http->FindSingleNode('.', $roots->item(0)), $m)
//                || preg_match("/^(?<name>[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s+{$patterns['separator']}\s*\|\s*(?i)My Account$/u", $this->http->FindSingleNode('.', $roots->item(0)), $m)
            )
        ) {
            // Todd Sullivan – 56489095 | My Account
            $name = $m['name'];
//            if ( !empty($m['number']) ) {
            $accountNumber = $m['number'];
//            }
        }

        // it-62622826.eml
        $xpathAccNum = '//*[ count(tr)=2 and tr[1][normalize-space()] and tr[2][starts-with(normalize-space(),"Account Number:")] ]';

        if ($this->http->XPath->query($xpathAccNum)->length > 0) {
            $name = $this->http->FindSingleNode($xpathAccNum . '/tr[1]', null, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');

            $accountNumber = $this->http->FindSingleNode($xpathAccNum . '/tr[2]', null, true, '/^Account Number:\s*([A-Z\d]{5,})$/i');

            // Tier Credits - основной баланс; MGM Rewards Points(Points) -  баланс субаккаунта, не собираем
            $points = $this->http->FindSingleNode($xpathAccNum . '/following::tr[not(.//tr) and starts-with(normalize-space(),"Tier Credits:")]', null, true, '/^Tier Credits:\s*(\d[,.\'\d ]*)$/');


            $HGSPoints = $this->http->FindSingleNode($xpathAccNum . '/following::tr[not(.//tr) and starts-with(normalize-space(),"HGS Points")]', null, true, '/^HGS Points:\s*(\d[,.\'\d ]*)$/');

            $asOf = $this->http->FindSingleNode($xpathAccNum . '/following::tr[not(.//tr) and starts-with(normalize-space(),"Account balances as of")]', null, true, '/^Account balances as of\s+(\d{1,2}\/\d{1,2}\/\d{4})(?:\s*[,.;!?]|$)/');
            $tierLevel = $this->http->FindSingleNode($xpathAccNum . '/following::tr[not(.//tr) and '.$this->starts(["Tier Level:", "Tier Status:"]).']', null, true, '/^\s*Tier (?:Level|Status):\s*(\D+)$/');

            $expressComps = $this->http->FindSingleNode($xpathAccNum . '/following::tr[not(.//tr) and starts-with(normalize-space(),"Express Comps")]', null, true, '/^Express Comps[™\s]*:\s*(.*\d.*)$/');
            $slotDollar = $this->http->FindSingleNode($xpathAccNum . '/following::tr[not(.//tr) and '.$this->starts(["SLOT DOLLARS®:", "Slot Dollars:"]).']', null, true, '/^\s*Slot Dollars®?\s*:\s*(.+)$/ui');


            $st = $email->add()->statement();

            $st->addProperty('Status', $tierLevel)
                ->addProperty('HGSPoints', $this->normalizeAmount($HGSPoints))
            ;

            if ($expressComps !== null) {
                $st->addProperty('ExpressCopms', $expressComps);
            } else {
                $st->addProperty('SlotDollars', $slotDollar);
            }


            if (empty($points) && empty($this->http->FindSingleNode($xpathAccNum . '/following::tr[not(.//tr) and starts-with(normalize-space(),"Tier ")][1]'))) {
                $st->setNoBalance(true);
            } else {
                $st
                    ->setBalance($this->normalizeAmount($points))
                    ->parseBalanceDate($asOf)
                ;
            }

        } elseif (!empty($accountNumber)) {
            if (!isset($st)) {
                $st = $email->add()->statement();
            }
            $st->setNoBalance(true);
        }

        if (!empty($name)) {
            if (!isset($st)) {
                $st = $email->add()->statement();
            }
            $st->addProperty('Name', $name);
        }

        if ($accountNumber !== null) {
            if (!isset($st)) {
                $st = $email->add()->statement();
            }
            $st->setNumber($accountNumber);
        }

        if (!isset($st)) {
            $text = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'This email is intended for')]/ancestor::*[1]");

            if (preg_match("/This email is intended for ([[:alpha:] \-]+), (?:M life®?|MGM) Rewards®? Account (\d{4,})(?: and|\.)/u", $text, $m)) {
                $st = $email->add()->statement();
                $st
                    ->addProperty('Name', $m[1])
                    ->setNumber($m[2])
                    ->setMembership(true)
                    ->setNoBalance(true)
                ;
            } elseif (preg_match("/This email is intended for ([[:alpha:] \-]+)\./u", $text, $m)
                && $this->http->FindSingleNode("(//text()[contains(translate(normalize-space(),'0123456789','dddddddddd'),'ddddd')])[1]")) {
                $email->setIsJunk(true);
            }
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return [];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query('//tr[ count(*)=2 and *[1][normalize-space()] and *[2][normalize-space()="View Your Account"] ]');
    }

    private function findRoot2(): \DOMNodeList
    {
        $xpathBtn = '(normalize-space()="My Account")';

        return $this->http->XPath->query("//h1[ descendant::a[{$xpathBtn}] ] | //tr[descendant::a[{$xpathBtn} and not(ancestor::h1)] and not(.//tr)]");
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }
}
