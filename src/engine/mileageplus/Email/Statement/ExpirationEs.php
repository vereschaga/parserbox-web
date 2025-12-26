<?php

namespace AwardWallet\Engine\mileageplus\Email\Statement;

class ExpirationEs extends \TAccountChecker
{
    // Flight delay - UAXXXX departing ABC
    // Flight reschedule - UAXXXX departing ABC

    protected $months = [
        "de enero de"      => "january",
        "de febrero de"    => "february",
        "de marzo de"      => "march",
        "de abril de"      => "april",
        "de mayo de"       => "may",
        "de junio de"      => "june",
        "de julio de"      => "july",
        "de agosto de"     => "august",
        "de septiembre de" => "september",
        "de octubre de"    => "october",
        "de noviembre de"  => "november",
        "de diciembre de"  => "december",
    ];

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
        return isset($headers['subject']) && preg_match("/No deje que expiren sus [\d\.]+ millas premio/", $headers['subject'])
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

    public static function getEmailLanguages()
    {
        return ["es"];
    }

    protected function ParseEmail()
    {
        $props = [];
        $info = $this->http->FindSingleNode("//text()[contains(., 'MileagePlus') and contains(., 'millas premio') and contains(., 'expirarán el')]");

        if ($info and preg_match("/Sus ([\d.]+) millas premio.+ de MileagePlus.+ expirarán el (.+)$/", $info, $m)) {
            $props["Balance"] = str_replace(".", "", $m[1]);
            $m[2] = str_ireplace(array_keys($this->months), array_values($this->months), $m[2]);
            $exp = strtotime($m[2]);

            if ($exp > strtotime("2000/01/01")) {
                $props["AccountExpirationDate"] = $exp;
            }
        }
        $number = $this->http->FindSingleNode("//text()[contains(., 'XXXX') and contains(., 'MileagePlus')]", null, true, "/X+(\d+)$/");

        if ($number) {
            $props["PartialLogin"] = $props["PartialNumber"] = $number . "$";
        }

        return $props;
    }
}
