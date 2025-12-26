<?php

namespace AwardWallet\Engine\tcase\Email;

class LoadedYourTrip extends \TAccountChecker
{
    public $mailFiles = "tcase/it-10071793.eml";

    public $reFrom = "@tripcase.com";
    public $reSubject = [
        "en" => "we loaded your trip to",
    ];
    public $reBody = 'Tripcase';
    public $reBody2 = [
        "en" => "View your entire itinerary in TripCase",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";
    public $emailSubject;

    public function parseHtml(&$itineraries)
    {
        $it = [];
        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[normalize-space(.) = 'Confirmation code:']/following::text()[normalize-space(.)][1]", null, true, "#^\s*([A-Z\d]{5,7})\s*$#");

        // TripNumber
        // Passengers
        if (preg_match("#:?([A-Z ]+), we loaded your trip to .+ into TripCase\. Download the free app today!#", $this->emailSubject, $m)) {
            $it['Passengers'][] = trim($m[1]);
        }

        // AccountNumbers
        // Cancelled
        // TotalCharge
        // BaseFare
        // Currency
        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory
        $year = $this->http->FindSingleNode("(//text()[starts-with(normalize-space(), '©')])[1]", null, true, "#©\s*\d{4}-(\d{4}) \D+#");

        $xpath = "//img[contains(@src,'air_segment')]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            return null;
        }

        foreach ($nodes as $root) {
            $dateStr = $this->http->FindSingleNode("./preceding::text()[normalize-space(.)][1]", $root);

            if (preg_match("#(\w+),\s*(\w+)\s+(\d+)#", $dateStr, $m)) {
                $date = \AwardWallet\Common\Parser\Util\EmailDateHelper::parseDateUsingWeekDay(
                        $m[3] . ' ' . $m[2] . ((!empty($year)) ? ' ' . $year : ''),
                         \AwardWallet\Engine\WeekTranslate::number1($m[1])
                        );
            }
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $itsegment */
            $itsegment = [];

            // FlightNumber
            // AirlineName
            $node = $this->http->FindSingleNode("(./descendant::td[1]/following-sibling::td[2]//td[normalize-space(.)])[2]", $root);

            if (preg_match("#(.+)\s+(\d{1,5})\s*$#", $node, $m)) {
                $itsegment['FlightNumber'] = $m[2];
                $itsegment['AirlineName'] = trim($m[1]);
            }

            // DepName
            // ArrName
            $node = $this->http->FindSingleNode("(./descendant::td[1]/following-sibling::td[2]//td[normalize-space(.)])[1]", $root);
            $node = explode("-", $node);

            if (count($node) == 2) {
                $itsegment['DepName'] = trim($node[0]);
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
                $itsegment['ArrName'] = trim($node[1]);
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            // DepDate
            $time = $this->http->FindSingleNode(".//text()[contains(normalize-space(), 'Depart')]/following::text()[normalize-space()][1]", $root);

            if (!empty($time) && !empty($date)) {
                $itsegment['DepDate'] = strtotime($time, $date);
            }

            // ArrDate
            if ($itsegment['DepDate']) {
                $itsegment['ArrDate'] = MISSING_DATE;
            }

            // Operator
            // Aircraft
            // TraveledMiles
            // Cabin
            // BookingClass
            // PendingUpgradeTo
            // Seats
            // Duration
            // Meal
            // Smoking
            // Stops
            $it['TripSegments'][] = $itsegment;
        }
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
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) > 0) {
            return false;
        }
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->reBody) === false) {
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
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) > 0) {
            return false;
        }
        $this->emailSubject = $parser->getSubject();
        $this->itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($this->itineraries);

        $result = [
            'emailType'  => 'LoadedYourTrip' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->itineraries,
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
        ];
        $out = [
        ];

        $str = preg_replace($in, $out, trim($str));

        if (preg_match("#[^\d\s-\./:apm]#i", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

        return $str;
    }
}
