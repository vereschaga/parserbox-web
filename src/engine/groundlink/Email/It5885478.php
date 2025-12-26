<?php

namespace AwardWallet\Engine\groundlink\Email;

class It5885478 extends \TAccountChecker
{
    public $mailFiles = "groundlink/it-5885478.eml";
    public $reFrom = "@groundlink.com";
    public $reSubject = [
        "en"=> "A new request has been submitted by info",
    ];
    public $reBody = 'groundlink.com';
    public $reBody2 = [
        "en"=> "RIDE DETAILS",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";
        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Reservation #')]", null, true, "#Reservation \#(\d+)#");

        // TripNumber
        // Passengers
        $it['Passengers'] = array_filter([$this->nextText("Passenger Name")]);

        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->re("#\D+([\d\,\.]+)$#", $this->nextText("Estimated Fare")));

        // BaseFare
        $it['BaseFare'] = $this->amount($this->re("#\D+([\d\,\.]+)$#", $this->nextText("Base Charge")));

        // Currency
        $it['Currency'] = $this->currency($this->nextText("Estimated Fare"));

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory
        $it['TripCategory'] = TRIP_CATEGORY_TRANSFER;
        $itsegment = [];

        // DepCode
        $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

        // DepName
        $itsegment['DepName'] = $this->nextText("Pickup Location");

        // DepDate
        $itsegment['DepDate'] = strtotime($this->normalizeDate($this->nextText("Pickup Date & Time")));

        // ArrCode
        $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

        // ArrName
        $itsegment['ArrName'] = $this->nextText("Dropoff Location");

        // ArrDate
        $itsegment['ArrDate'] = MISSING_DATE;

        $itsegment['Type'] = $this->nextText('Trip Type');

        $itsegment['Vehicle'] = $this->nextText('Car Class');

        $it['TripSegments'][] = $itsegment;

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];
        $this->http->setBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

        foreach ($this->reBody2 as $lang=> $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function nextText($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[normalize-space(.)='{$field}'])[{$n}]/following::text()[normalize-space(.)][1]", $root);
    }

    private function nextCol($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//td[not(.//td) and normalize-space(.)='{$field}'])[{$n}]/following-sibling::td[1]", $root);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})\s+at\s+(\d+:\d+[AP]M)$#",
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }
}
