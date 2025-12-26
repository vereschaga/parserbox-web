<?php

namespace AwardWallet\Engine\topcashback\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class TopCashback extends \TAccountChecker
{
    public $mailFiles = "topcashback/statements/it-71429806.eml, topcashback/statements/it-71928423.eml, topcashback/statements/it-78022215.eml";

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]topcashback\.(?:com|co\.uk)/i', $from) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".topcashback.com/") or contains(@href,".topcashback.co.uk/") or contains(@href,"www.topcashback.com") or contains(@href,"www.topcashback.co.uk")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"www.topcashback.com") or contains(.,"www.topcashback.co.uk")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1
            || $this->http->XPath->query("//table[starts-with(normalize-space(),'You have') and contains(normalize-space(),'cash back payable')]")->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $member = $login = null;
        $st = $email->add()->statement();

        $roots = $this->findRoot();

        if ($roots->length === 1) {
            $root = $roots->item(0);
            $footerHtml = $this->http->FindHTMLByXpath('.', null, $root);
            $footerText = $this->htmlToText($footerHtml);

            if (preg_match("/^[ ]*Member:[ ]*\([ ]*([^)(\n]+?)[ ]*\)[ ]*$/im", $footerText, $m)) {
                // Member: (dimitka)
                $member = $m[1];
            }

            if ($member && preg_match("/^[ ]*Sent To Email:[ ]*\([ ]*(\S*@\S*?)[ ]*\)[ ]*$/im", $footerText, $m)) {
                // Sent To Email: (kievchicago@yahoo.com)
                $login = $m[1];
            }
        }

        if (!$login) {
            // it-78022215.eml
            $login = $this->http->FindSingleNode("//text()[normalize-space()='This email was sent to:']/following::text()[normalize-space()][1]", null, true, "/^\S+@\S+\w$/");
        }

        if ($member) {
            $st->addProperty('AccountNumber', $member);
        }

        if ($login) {
            $st->setLogin($login);
        }

        if ($member || $login) {
            $st->setNoBalance(true);
        }

        $email->setType('TopCashback');

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        $nodes = $this->http->XPath->query("//text()[starts-with(normalize-space(),'Sent To Email:')]/ancestor::*[ descendant::text()[starts-with(normalize-space(),'Member:')] ][1]");

        return $nodes;
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
