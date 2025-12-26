<?php

namespace AwardWallet\Engine\mileageplus\Email\Statement;

class BonusMiles extends \TAccountChecker
{
    public $mailFiles = "mileageplus/statements/st-5769000.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $props = $this->ParseEmail();

        return [
            'parsedData' => ["Properties" => $props],
            'emailType'  => "BonusMiles",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@news.united.com') !== false
            && isset($headers['subject']) && stripos($headers['subject'], 'bonus miles') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//tr[not(.//tr)
			and .//a[contains(normalize-space(.), "My account")]
			and .//a[contains(normalize-space(.), "Earn miles")]
			and .//a[contains(normalize-space(.), "Use miles")]]')->length > 0
        && strpos($this->http->Response['body'], 'Balance as of') !== false
        && strpos($this->http->Response['body'], 'Bonus after you spend') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]united\./", $from);
    }

    protected function ParseEmail()
    {
        $r = [];
        $number = $this->http->FindSingleNode("//text()[contains(., 'XXXXX')]", null, true, "/XXXXX([A-Z\d]{3})/");

        if ($number) {
            $r["PartialLogin"] = $r["PartialNumber"] = $number . "$";
        }
        $balance = $this->http->FindSingleNode('//td[contains(., "Balance as of") and not(.//td)]', null, true, '/^([\d,]+)\s*Balance as of/');

        if (isset($balance)) {
            $r['Balance'] = str_replace(',', '', $balance);
        }

        return $r;
    }
}
