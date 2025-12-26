<?php

namespace AwardWallet\Engine\rapidrewards\Email\Statement;

class BalanceUpdate extends \TAccountChecker
{
    public $mailFiles = "";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match("/RapidRewards@luv\.southwest\.com/i", $headers['from']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return $this->http->XPath->query("//a[starts-with(@href, 'http://luv.southwest.com/servlet/') or starts-with(@href, 'https://luv.southwest.com/pub/cc')]")->length > 0 && stripos($body, "Rapid Rewards #") !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return (bool) preg_match("/\.southwest\.com/", $from);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $props = $this->ParseEmail();

        return [
            'parsedData' => ["Properties" => $props],
            'emailType'  => "BalanceUpdate",
        ];
    }

    public static function getEmailTypesCount()
    {
        return 4;
    }

    protected function ParseEmail()
    {
        $result = [];
        $result["Name"] = $this->http->FindSingleNode("//tr[normalize-space(.) = 'Name:']/following-sibling::tr[normalize-space(.) != ''][1]");

        if (!isset($result["Name"])) {
            $result["Name"] = $this->http->FindSingleNode("//*[starts-with(normalize-space(text()), 'Welcome,')]/text()[1]", null, true, "/Welcome, (.+)$/");
        }

        if (!isset($result["Name"])) {
            $result["Name"] = $this->http->FindSingleNode("//*[starts-with(normalize-space(text()), 'Hello,')]/text()[1]", null, true, "/Hello, (.+)$/");
        }

        if (!isset($result["Name"])) {
            $result["Name"] = $this->http->FindSingleNode("//*[starts-with(normalize-space(text()), 'Dear ')]/text()[1]", null, true, "/Dear (.+):$/");
        }
        $balance = $this->http->FindSingleNode("//tr[starts-with(normalize-space(.), 'Balance as of')]/following-sibling::tr[normalize-space(.) != ''][1]", null, true, '/^([\d\,]+) points$/');

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//tr[starts-with(normalize-space(.), 'Your balance as of') and not(.//tr)]/td[last()]", null, true, "/^([\d\,]+) points$/");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//*[text()[starts-with(normalize-space(.), 'You have')] and contains(., 'points in your Rapid Rewards')]", null, true, "/You have ([\d\,]+)\*? points in your Rapid Rewards/");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//*[contains(text(), 'Available Points')]", null, true, "/^Available Points\D+(\d+)$/");
        }

        if (isset($balance)) {
            $result["Balance"] = str_replace(",", "", $balance);
        }

        $result["Number"] = $result["Login"] = $this->http->FindSingleNode("//*[contains(text(), 'Rapid Rewards #')]", null, true, "/Rapid Rewards \#(\d+)$/");

        return $result;
    }
}
