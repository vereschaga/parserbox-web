<?php

namespace AwardWallet\Engine\troon\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class EStatement extends \TAccountChecker
{
    public $mailFiles = "troon/statements/it-76368100.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@troon-golf.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Troon Rewards E-Statement') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".troon-golf.com/") or contains(@href,"links.troon-golf.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"@troon-golf.com")]')->length === 0
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

        $st = $email->add()->statement();

        $name = $number = $status = $login = null;

        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $row1Html = $this->http->FindHTMLByXpath('ancestor::table[1]/preceding-sibling::table[normalize-space()][1]/descendant::tr[not(.//tr) and normalize-space()][1]', null, $root);
        $row1Text = $this->htmlToText($row1Html);

        if (preg_match("/^(?<name>{$patterns['travellerName']})[ ]*\n+[ ]*(?<number>[-A-Z\d]{5,})$/u", $row1Text, $m)) {
            /*
                Roxana Miller
                883881
            */
            $name = $m['name'];
            $number = $m['number'];
            $st->addProperty('Name', $name)
                ->setNumber($number);
        }

        $balance = $this->http->FindSingleNode("*[2]", $root, true, "/^\d[,.\'\d ]*$/");
        $st->setBalance($this->normalizeAmount($balance));

        $asOf = $this->http->FindSingleNode("ancestor::table[1]/preceding-sibling::table[normalize-space()][1]/descendant::tr[not(.//tr) and starts-with(normalize-space(),'As of')][1]", $root, true, "/^As of\s+(.{6,})$/");
        $st->parseBalanceDate($asOf);

        $statusVariants = '(Member|Silver|Gold|Platinum)';
        $status = $this->http->FindSingleNode("following-sibling::tr[ *[1][starts-with(normalize-space(),'Rewards Status')] ]/*[2]", $root, true, "/^{$statusVariants}$/i");
        $st->addProperty('Status', $status);

        $login = $this->http->FindSingleNode("//text()[normalize-space()='This email was sent to']/following::text()[normalize-space()][1]", null, true, "/^\S+@\S+$/");

        if ($login) {
            $st->setLogin($login);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//tr[ *[1][starts-with(normalize-space(),'Redeemable Points')] and *[2][normalize-space()] ]");
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
