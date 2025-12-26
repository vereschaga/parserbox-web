<?php

namespace AwardWallet\Engine\wellsfargo\Email\Statement;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourRewards extends \TAccountChecker
{
    public $mailFiles = "wellsfargo/statements/it-111829316.eml, wellsfargo/statements/it-139517332.eml, wellsfargo/statements/it-139397514.eml, wellsfargo/statements/it-139400515.eml, wellsfargo/statements/it-138059662.eml";

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]gofarrewards\.wellsfargo\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Your Go Far Rewards Summary for') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".mywellsfargorewards.com/") or contains(@href,"mail2.mywellsfargorewards.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"GoFarRewards.wf.com")]')->length === 0
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

        // Hi Keegan, here's your Go Far Rewards summary as of September 8, 2021.
        $rootText = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $root));

        if (preg_match("/^Hi\s*({$patterns['travellerName']})\s*(?:[,;:!?]|$)/iu", $rootText, $m)) {
            $st->addProperty('Name', $m[1]);
        }

        $rewardsID = $this->http->FindSingleNode("//text()[normalize-space()='REWARDS ACCOUNT NUMBER:' or normalize-space()='Rewards account number']/following::text()[string-length(normalize-space())>3][1]", null, true, "/^.*\d.*$/");
        if ($rewardsID) {
            // not collect, because masked
//            $st->addProperty('RewardsID', $rewardsID);
        }
        $account = $this->http->FindSingleNode("//text()[normalize-space()='for card ending in']/following::text()[string-length(normalize-space())>3][1]", null, true, "/^.*\d.*$/");
        if ($account) {
            $st->setNumber($account)->masked('left');
        }

        $balance = null;
        $balanceValue = $this->http->FindSingleNode("//text()[normalize-space()='Available rewards points:' or normalize-space()='Available rewards points']/following::text()[normalize-space()][1]", null, true, "/^.*\d.*$/")
            ?? $this->http->FindSingleNode("//text()[normalize-space()='Available cash rewards:' or normalize-space()='Available cash rewards']/following::text()[normalize-space()][1]", null, true, "/^.*\d.*$/")
        ;

        if (preg_match("/^\d[,.\'\d ]*$/", $balanceValue)) {
            // it-111829316.eml, it-139397514.eml, it-138059662.eml
            $balance = PriceHelper::parse($balanceValue);
        } elseif (preg_match("/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/", $balanceValue, $matches)) {
            // it-139517332.eml, it-139400515.eml
            $balance = PriceHelper::parse($matches['amount']);
            $st->addProperty('CurrencyType', 'CASH');
        }

        if ($balance !== null) {
            $st->setBalance($balance);

            if (preg_match("/summary as of\s+(.{6,}?)\s*$/i", $rootText, $m)) {
                $st->parseBalanceDate($m[1]);
            }
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//tr[not(.//tr) and (contains(normalize-space(),'your Go Far Rewards summary') or contains(normalize-space(),'your Go Far® Rewards summary'))]");
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
