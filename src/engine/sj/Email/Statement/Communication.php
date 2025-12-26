<?php

namespace AwardWallet\Engine\sj\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Communication extends \TAccountChecker
{
    public $mailFiles = "sj/it-453030275.eml, sj/statements/it-84945398.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@kommunikation.sj.se') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".sj.se/") or contains(@href,"kommunikation.sj.se")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"www.sj.se") or contains(@href,"kommunikation.sj.se")]')->length === 0
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

        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        $name = $number = $status = $balance = $login = null;

        $name = $this->http->FindSingleNode("preceding::tr[normalize-space()][1]", $root, true, "/^{$patterns['travellerName']}$/u");
        $st->addProperty('Name', $name);

        $cellLeftText = $this->htmlToText($this->http->FindHTMLByXpath('*[1]', null, $root));

        /*
            Medlemsnr: 9752 2102 5107 1938
            Medlemsnivå: Vit
        */

        if (preg_match("/^[ ]*Medlemsnr[ ]*[:]+[ ]*([-A-Z\d ]{5,}?)[ ]*$/m", $cellLeftText, $m)) {
            $number = str_replace(' ', '', $m[1]);
        }

        if ($number) {
            $st->setNumber($number);
        }

        if (preg_match("/^[ ]*Medlemsnivå[ ]*[:]+[ ]*(.{3,}?)[ ]*$/m", $cellLeftText, $m)) {
            $status = str_replace(' ', '', $m[1]);
        }

        if ($status) {
            $st->addProperty('Tier', $status);
        }

        $balance = $this->http->FindSingleNode("*[2]/descendant::tr[normalize-space()='Poäng att använda']/preceding-sibling::tr[normalize-space()]", $root, true, '/^\d[,.\'\d ]*$/');

        if ($balance == null) {
            $balance = $this->http->FindSingleNode("*[2]/following::tr[normalize-space()='Poäng att använda']/preceding-sibling::tr[normalize-space()]", $root, true, '/^\d[,.\'\d ]*$/');
        }
        $st->setBalance($this->normalizeAmount($balance));

        $login = $this->http->FindSingleNode("//text()[normalize-space()='Detta mejl är skickat till']/following::text()[normalize-space()][1]", $root, true, '/^\S+@\S+$/');
        $st->setLogin($login);

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        $nodes = $this->http->XPath->query("//tr[ count(*)=2 and *[1][descendant::text()[starts-with(normalize-space(),'Medlemsnr')] and descendant::text()[starts-with(normalize-space(),'Medlemsnivå')]] and *[2][contains(normalize-space(),'Poäng att använda')] ]");

        if ($nodes->length == 0) {
            $nodes = $this->http->XPath->query("//tr[ count(*)=2 and *[1][descendant::text()[starts-with(normalize-space(),'Medlemsnr')] and descendant::text()[starts-with(normalize-space(),'Medlemsnivå')]] or *[2][contains(normalize-space(),'Poäng att använda')] ]");
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
