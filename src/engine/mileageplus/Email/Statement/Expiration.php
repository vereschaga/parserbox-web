<?php

namespace AwardWallet\Engine\mileageplus\Email\Statement;

class Expiration extends \TAccountChecker
{
    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $props = $this->ParseEmail();

        return [
            'parsedData' => ["Properties" => $props],
            'emailType'  => "Expiration",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && preg_match("/let your [\d.,]+ award miles expire/", $headers['subject'])
            || isset($headers['from']) && stripos($headers['from'], 'MileagePlus@news.united.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]united\./", $from);
    }

    // Flight delay - UAXXXX departing ABC
    // Flight reschedule - UAXXXX departing ABC

    protected function ParseEmail()
    {
        $props = [];
        $info = $this->http->FindSingleNode("//text()[contains(., 'MileagePlus') and contains(., 'award miles') and contains(., 'are due to expire')]");

        if ($info and preg_match("/Your ([\d,]+) MileagePlus.+award miles.+are due to expire on (.+)$/", $info, $m)) {
            $props["Balance"] = str_replace(",", "", $m[1]);
            $exp = strtotime($m[2]);

            if ($exp > strtotime("2000/01/01")) {
                $props["AccountExpirationDate"] = $exp;
            }
        }
        $number = $this->http->FindSingleNode("//text()[contains(., 'XXXX') and contains(., 'MileagePlus')]", null, true, "/X+(\d+)$/");

        if ($number) {
            $props["PartialLogin"] = $props["PartialNumber"] = $number . "$";
        }
        $props["Name"] = $this->http->FindSingleNode("//text()[contains(., 'your miles can take you places')]", null, true, "/^(.+), your miles can take you places/");

        return $props;
    }
}
