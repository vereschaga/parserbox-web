<?php

namespace AwardWallet\Engine\petperks\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Subscription extends \TAccountChecker
{
    public $mailFiles = "petperks/statements/it-68872280.eml, petperks/statements/it-70686429.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@mail.petsmart.com') !== false
            || stripos($from, '@emails.petsmart.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".petsmart.com/") or contains(@href,"mail.petsmart.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"download the FREE PetSmart app")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            return $email;
        }
        $root = $roots->item(0);
        $email->setType('Subscription');

        $st = $email->add()->statement();

        $headerText = $this->http->FindSingleNode('.', $root);
        /*
            hi, Gir

            or

            hi, Ryan | 491 pts.

            or

            hi, Neal | $2.50
        */

        $name = null;

        if (preg_match("/^\s*hi,\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]|[[:alpha:]])(?:\s*[,:;!?|]|$)/imu", $headerText, $m)) {
            $name = $m[1];
        }
        $st->addProperty('Name', $name);

        $pointsBalance = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Points Balance:')]/ancestor::*[ following-sibling::*[normalize-space()] ][1]", null, true, "/Points Balance:\s*(\d[,.\'\d ]*)$/i");

        if ($pointsBalance !== null) {
            $st->setBalance($this->normalizeAmount($pointsBalance));
        } elseif (preg_match("/(?:^|\|)\s*(\d[,.\'\d ]*)pts[,.!]*$/i", $headerText, $m)) {
            $st->setBalance($this->normalizeAmount($m[1]));
        } elseif ($name) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//*[ count(*)=2 and count(tr)=2 and *[1][normalize-space()=''] ]/tr[2][normalize-space() and starts-with(normalize-space(),'hi,')]");
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
