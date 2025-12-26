<?php

namespace AwardWallet\Engine\mileageplus\Email\Statement;

class CardStatement extends \TAccountChecker
{
    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $props = $this->ParseEmail();

        return [
            'parsedData' => ["Properties" => $props],
            'emailType'  => "CardStatement",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'MileagePlus@news.united.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//a[contains(., 'Learn more') and contains(@href, 'http://news.united.com/servlet/')]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]united\./", $from);
    }

    // email with ad about applying for a card

    protected function ParseEmail()
    {
        $props = [];
        $number = $this->http->FindSingleNode("//text()[contains(., 'XXXX') and contains(., 'MileagePlus')]", null, true, "/X+(\d+)$/");

        if ($number) {
            $props["PartialLogin"] = $props["PartialNumber"] = $number . "$";
        }
        $balance = $this->http->FindSingleNode("//tr[td[contains(., 'Balance as of') and not(.//td)]]/td[1]");

        if (preg_match("/^[\d,]+$/", $balance)) {
            $props["Balance"] = str_replace(",", "", $balance);
        }

        return $props;
    }
}
