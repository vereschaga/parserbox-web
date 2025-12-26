<?php

namespace AwardWallet\Engine\qmiles\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class MonthlyStatement2023 extends \TAccountChecker
{
    public $mailFiles = "qmiles/statements/it-378009988.eml, qmiles/statements/it-660990636.eml";
    // subject: , your monthly statement has arrived

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'email@qr.qatarairways.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".qatarairways.com/") or contains(@href,"qr.qatarairways.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"You are receiving this email from Qatar Airways Privilege Club")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            return $email;
        }
        $root = $roots->item(0);

        $patterns['travellerName'] = '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("descendant::text()[normalize-space()][1]", $root, true, "/^\s*({$patterns['travellerName']})\s*$/u");
        $name = preg_replace("/^(?:MRS|MR|MS|DR)[.\s]+(.+)$/i", '$1', $name);
        $st->addProperty('Name', $name);

        $number = $this->http->FindSingleNode("descendant::text()[normalize-space()][4]", $root, true, "/^\s*#\s*(\d{5,})\s*$/");
        $st->setNumber($number);

        $status = $this->http->FindSingleNode("descendant::text()[normalize-space()][3]", $root, true, "/^\s*([[:alpha:]]+)\s*$/");
        $st->addProperty('MembershipLevel', $status);

        $qpointsNextLevel = $this->http->FindSingleNode("descendant::text()[normalize-space()][5]", $root, true, "/^\s*\+\s*(\d+)\s*Qpoints to/");
        $st->addProperty('QpointsNextLevel', $qpointsNextLevel);

        $balance = $this->http->FindSingleNode("descendant::text()[normalize-space() = 'Your Avios']/following::text()[normalize-space()][1]", $root, true, "/^\s*(\d[\d, ]*)\s*$/");
        $balance = preg_replace('/\W/', '', $balance);
        $st->setBalance($this->normalizeAmount($balance));

        $qPoints = $this->http->FindSingleNode(".//tr[*[1][(normalize-space()='Qpoints Balance' or normalize-space()='Qpoints')] and *[2][normalize-space()='Qcredits']]/following-sibling::tr/*[1]", $root, true, "/^\d[,.\'\d ]*$/");

        if ($qPoints === null) {
            $qPoints = $this->http->FindSingleNode(".//tr[*[2][(normalize-space()='Qpoints')] and *[3][normalize-space()='Qcredits']]/following-sibling::tr/*[2]", $root, true, "/^\d[,.\'\d ]*$/");
        }
        $st->addProperty('CurrentQpoints', $qPoints);

        $qCredits = $this->http->FindSingleNode(".//tr[*[1][(normalize-space()='Qpoints Balance' or normalize-space()='Qpoints')] and *[2][normalize-space()='Qcredits']]/following-sibling::tr/*[2]", $root, true, "/^\d[,.\'\d ]*$/");

        if ($qCredits === null) {
            $qCredits = $this->http->FindSingleNode(".//tr[*[2][(normalize-space()='Qpoints')] and *[3][normalize-space()='Qcredits']]/following-sibling::tr/*[3]", $root, true, "/^\d[,.\'\d ]*$/");
        }
        $st->addProperty('Qcredits', $qCredits);

        $balanceDate = $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(),'Valid at') or starts-with(normalize-space(),'Balance as of')]", $root, true, "/(?:Valid at|Balance as of)\s+(.*\d.*)$/i");
        $st->parseBalanceDate($balanceDate);

        $expiryDate = $this->http->FindSingleNode(".//tr[*[1][(normalize-space()='Avios expiry date')] and *[2][(normalize-space()='Qpoints')] and *[3][normalize-space()='Qcredits']]/following-sibling::tr/*[1]", $root, true, "/.*\d.*/");

        if ($expiryDate !== null) {
            $st->parseExpirationDate($expiryDate);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        $roots = $this->http->XPath->query("//text()[normalize-space()='Privilege Club']/ancestor::table[1][following::text()[normalize-space()][1][normalize-space()='Your Avios']]/ancestor::*[contains(.,'Your Avios')][1][descendant::text()[normalize-space()][2][normalize-space()='Privilege Club']]");

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
