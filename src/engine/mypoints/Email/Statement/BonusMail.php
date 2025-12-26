<?php

namespace AwardWallet\Engine\mypoints\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class BonusMail extends \TAccountChecker
{
    public $mailFiles = "mypoints/statements/it-74457339.eml, mypoints/statements/it-73622508.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@mypoints.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".mypoints.com/") or contains(@href,"www.mypoints.com") or contains(@href,"api.mypoints.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"MyPoints.com, LLC. All rights reserved")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1 || $this->isMembership();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            if ($this->isMembership()) {
                $st->setMembership(true);
            }

            return $email;
        }
        $root = $roots->item(0);

        $points = $this->http->FindSingleNode('.', $root, true, "/(?:^|\|[ ]*)(\d[,.\'\d ]*)[ ]*PTS$/i");
        $st->setBalance($this->normalizeAmount($points));

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//tr[not(.//tr) and descendant::a[contains(.,'PTS')] and contains(.,'|') and count(preceding::tr[normalize-space()])=1]");
    }

    private function isMembership(): bool
    {
        return $this->http->XPath->query("//*[contains(normalize-space(),\"You've received this email advertisement because you're a member of MyPoints\")]")->length > 0;
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
