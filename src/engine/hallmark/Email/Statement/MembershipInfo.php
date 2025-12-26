<?php

namespace AwardWallet\Engine\hallmark\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class MembershipInfo extends \TAccountChecker
{
    public $mailFiles = "hallmark/statements/it-92252316.eml, hallmark/statements/it-92252352.eml, hallmark/statements/it-92252378.eml";

    private $type = '';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@e-mail.hallmark.com') !== false
            || stripos($from, '@hallmarkonline.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers['subject'], 'A warm welcome from Hallmark') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".hallmark.com/") or contains(@href,"e-mail.hallmark.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Hallmark.com/CrownRewards") or contains(normalize-space(),"Hallmark.com/MyCrownRewards")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query("//*[contains(normalize-space(),'We’re so glad you’ve signed up for a Hallmark.com account.')]")->length > 0
            || $this->findRoot()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        $name = $number = $balance = $login = null;

        $roots = $this->findRoot();

        if ($roots->length === 1) {
            $root = $roots->item(0);

            $rootText = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $root));
            /*
                Gordon Freeman
                Member: 913600545036
                Point balance: 0
                Rewards available: $0
            */
            $pattern1 = "/^\s*(?<name>{$patterns['travellerName']})[ ]*\n+[ ]+Member[ ]*:[ ]*(?<number>[-A-Z\d]{5,})[ ]*\n+[ ]*Point balance[ ]*:[ ]*(?<balance>\d[,.\'\d ]*)(?:\n|$)/u";

            /*
                CROWN REWARDS
                Gordon Freeman
                Member Number: 913600545036
            */
            $pattern2 = "/CROWN REWARDS[ ]*\n+[ ]*(?<name>{$patterns['travellerName']})[ ]*\n+[ ]*Member Number[ ]*:[ ]*(?<number>[-A-Z\d]{5,})\s*(?:\n|$)/u";

            if (preg_match($pattern1, $rootText, $m) || preg_match($pattern2, $rootText, $m)) {
                $name = $m['name'];
                $number = $m['number'];
                $st->setNumber($number);

                if (!empty($m['balance'])) {
                    $balance = $m['balance'];
                    $st->setBalance($this->normalizeAmount($balance));
                }
            }
        }

        if (!$name) {
            $nameNodes = array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(),'Hi')]", null, "/^Hi\s+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

            if (count(array_unique($nameNodes)) === 1) {
                $name = array_shift($nameNodes);
            }
        }

        if ($name) {
            $st->addProperty('Name', $name);
        }

        $login = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Thanks for subscribing')]/following::text()[normalize-space()][1]", null, true, "/^\S+@\S+$/");

        if ($login) {
            $st->setLogin($login);
        }

        if ($balance === null
            && ($name || $number || $login)
        ) {
            $st->setNoBalance(true);
        }

        $email->setType('MembershipInfo' . ucfirst($this->type));

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        $this->type = '1'; // it-92252378.eml
        $nodes = $this->http->XPath->query("//tr/*[not(.//tr) and contains(normalize-space(),'Member:') and contains(normalize-space(),'Point balance:')]");

        if ($nodes->length !== 1) {
            $this->type = '2'; // it-92252352.eml
            $nodes = $this->http->XPath->query("//tr/*[not(.//tr) and descendant::text()[normalize-space()][1][normalize-space()='CROWN REWARDS'] and contains(normalize-space(),'Member Number:')]");
        }

        return $nodes;
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
