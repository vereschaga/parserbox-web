<?php

namespace AwardWallet\Engine\mileageplus\Email\Statement;

class NextTripStatement extends \TAccountChecker
{
    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $props = $this->ParseEmail();

        return [
            'parsedData' => ["Properties" => $props],
            'emailType'  => "NextTripStatement",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, 'miles needed for your next adventure') !== false && stripos($body, 'http://news.united.com/servlet/') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]united\./", $from);
    }

    protected function ParseEmail()
    {
        $props = [];
        $tds = $this->http->FindNodes("//tr[td[contains(., 'miles needed for your next adventure') and not(.//td)]]/preceding-sibling::tr[1]/td");
        $balance = null;
        $ok = false;

        foreach ($tds as $td) {
            if ($td == 'miles earned') {
                $ok = true;
            }

            if (preg_match("/^[\d,]+$/", $td)) {
                $balance = str_replace(",", "", $td);
            }
        }

        if ($ok && isset($balance)) {
            $props["Balance"] = $balance;
        }
        $number = $this->http->FindSingleNode("//text()[contains(., 'XXXX') and contains(., 'MileagePlus')]", null, true, "/X+(\d+)$/");

        if ($number) {
            $props["PartialLogin"] = $props["PartialNumber"] = $number . "$";
        }

        return $props;
    }
}
