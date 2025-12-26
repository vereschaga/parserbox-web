<?php

namespace AwardWallet\Engine\pegasus\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class BolPointsDetails extends \TAccountChecker
{
    public $mailFiles = "pegasus/statements/it-78141889.eml, pegasus/statements/it-78141805.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@pegasusbolbol.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Pegasus BolBol Monthly Statement') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".flypgs.com/") or contains(@href,"www.flypgs.com") or contains(@href,"ff.flypgs.com")]')->length === 0
        ) {
            return false;
        }

        return $this->isMembership();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Dear')]", null, true, "/^Dear\s+({$patterns['travellerName']})(?:[ ]*[,:!?]|$)/u");
        $st->addProperty('Name', $name);

        $balance = $this->http->FindSingleNode("//tr/*[position()>1][starts-with(normalize-space(),'TOTAL BOLPOINTS IN YOUR ACCOUNT')]", null, true, "/^TOTAL BOLPOINTS IN YOUR ACCOUNT\s+(\d[,.\'\d ]*)(?:\s*BOLPUAN|$)/i");
        $st->setBalance($this->normalizeAmount($balance));

        $balanceDate = $this->http->FindSingleNode("//text()[normalize-space()='Detailed breakdown of your transactions during']/following::text()[normalize-space()][1]", null, true, "/^.{6,}\s+-\s+(.{6,})$/");
        $st->parseBalanceDate($balanceDate);

        $redeemedPoints = $this->http->FindSingleNode("//tr/*[position()>1][starts-with(normalize-space(),'REDEEMED BOLPOINTS')]", null, true, "/^REDEEMED BOLPOINTS\s+([- ]*\d[,.\'\d ]*)(?:\s*BOLPUAN|$)/i");
        $st->addProperty('RedeemedPoints', $redeemedPoints);

        $expiringPointsHtml = $this->http->FindHTMLByXpath("//tr[ *[position()>1][starts-with(normalize-space(),'EXPIRING BOLPOINTS')] ]/following-sibling::tr[normalize-space()][1]/descendant::*[@colspan='2']");
        $expiringPointsText = $this->htmlToText($expiringPointsHtml);

        $expiringPointsRows = preg_split("/[ ]*\n+[ ]*/", $expiringPointsText);
        $st->addProperty('RedeemedPoints', implode('; ', $expiringPointsRows));

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function isMembership(): bool
    {
        return $this->http->XPath->query("//*[contains(normalize-space(),\"This is an information message regarding your Pegasus BolBol frequent flyer program membership\") or contains(normalize-space(),\"As a Bolbol member you've collected a total of\")]")->length > 0;
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
