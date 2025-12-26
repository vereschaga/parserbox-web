<?php

namespace AwardWallet\Engine\voila\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class YourEStatement extends \TAccountChecker
{
    public $mailFiles = "voila/statements/it-12127351.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@vhr.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".vhr.com/") or contains(@href,"statement.vhr.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"www.vhr.com")]')->length === 0
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

        $name = $this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][1]", $root, true, "/^Hello[,\s]+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:\s*[,:;!?]|$)/iu");
        $st->addProperty('Name', $name);

        $number = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]", $root, true, "/^Member Number[:\s]+([-A-Z\d ]{5,})$/i");
        $st->setNumber($number);

        $balance = $this->http->FindSingleNode('.', $root, true, "/^Current Balance[:\s]+(\d[,.\'\d ]*)(?:\s*points?)?$/i");
        $st->setBalance($this->normalizeAmount($balance));

        $otherFieldsHtml = $this->http->FindHTMLByXpath("//tr/*[not(.//tr) and starts-with(normalize-space(),'Member Since') and descendant::text()[starts-with(normalize-space(),'Last Stay Date:')]]");
        $otherFields = $this->htmlToText($otherFieldsHtml);

        /*
            Member Since: 2010-12-05
            Last Stay Date:
            Last Stay Hotel: --

            Silver member: 0 nights stayed since 2018-12-05
        */

        if (preg_match("/^[ ]*Member Since[: ]+(.{6,}?)[ ]*$/im", $otherFields, $m)
            && !preg_match("/^[-\s]+$/", $m[1])
        ) {
            $st->addProperty('Since', $m[1]);
        }

        if (preg_match("/^[ ]*Last Stay Date[: ]+(.{6,}?)[ ]*$/im", $otherFields, $m)
            && !preg_match("/^[-\s]+$/", $m[1])
        ) {
            $st->addProperty('Laststaydate', $m[1]);
        }

        if (preg_match("/^[ ]*Last Stay Hotel[: ]+(.{3,}?)[ ]*$/im", $otherFields, $m)
            && !preg_match("/^[-\s]+$/", $m[1])
        ) {
            $st->addProperty('Laststayhotel', $m[1]);
        }

        if (preg_match("/^[ ]*(Silver|Gold|Platinum) member[: ]+.+$/im", $otherFields, $m)) {
            $st->addProperty('Status', $m[1]);
        }

        $email->setType('YourEStatement');

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//tr[starts-with(normalize-space(),'Member Number')]/preceding-sibling::tr[starts-with(normalize-space(),'Current Balance')]");
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
