<?php

namespace AwardWallet\Engine\mileageplus\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class FlightItineraryList extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-12920618.eml, mileageplus/it-5875148.eml, mileageplus/it-6362460.eml, mileageplus/it-7416608.eml, mileageplus/it-7416612.eml, mileageplus/it-7421426.eml";

    public $lang = "en";
    private $reFrom = "unitedairlines@united.com";
    private $reSubject = [
        "en" => "Travel Itinerary sent from United Airlines",
        'de' => 'Reiseplan von United Airlines',
    ];
    private $reBody = 'United Airlines';
    private $reBody2 = [
        "en" => "Flight Details:",
        'de' => 'Flugdetails:',
    ];

    private static $dictionary = [
        "en" => [],
        'de' => [
            'Traveler Information' => 'Informationen zum Reisenden',
            'Seat Assignments:'    => 'Sitzplatzreservierung:',
            'Confirmation Number:' => 'Bestätigungsnummer:',
            'Depart:'              => 'Abflug:',
            'Flight:'              => 'Flug:',
            'Arrive:'              => 'Ankunft:',
            'Operated By'          => 'Durchgeführt von',
            'Aircraft:'            => 'Flugzeug:',
            'Flight distance:'     => 'Gesamtentfernung:',
            'Fare Class:'          => 'Buchungsklasse:',
            'Flight Time:'         => 'Flugdauer:',
            //			'Travel Time:' => '',
            'Meal:' => 'Mahlzeit:',
        ],
    ];
    private $date = null;

    public function parseHtml()
    {
        $itineraries = [];
        $passengers = [];
        $seats = [];
        $xpath = "//text()[" . $this->contains($this->t('Traveler Information')) . "]/ancestor::b[1]/following-sibling::*/table[" . $this->contains($this->t('Seat Assignments:')) . "]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $passengers[] = $this->http->FindSingleNode('.//tr[1]', $root);
            $sts = $this->http->FindNodes(".//text()[" . $this->eq($this->t("Seat Assignments:")) . "]/ancestor::td[1]/following-sibling::td[1]//text()[normalize-space(.)]", $root);

            foreach ($sts as $s) {
                if (preg_match("#^(?<dep>[A-Z]{3}) - (?<arr>[A-Z]{3}): (?<seat>\d+[A-Z])$#", $s, $m)) {
                    $seats[$m['dep'] . '-' . $m['arr']][] = $m['seat'];
                }
            }
        }

        $it = [];
        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Confirmation Number:"));

        // Passengers
        $it['Passengers'] = $passengers;

        $xpath = "//text()[" . $this->eq($this->t("Depart:")) . "]/ancestor::ul[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])(\d+)$#", $this->nextText($this->t("Flight:"), $root));

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Depart:")) . "]/following::text()[normalize-space(.)][3]", $root, true, "#\(([A-Z]{3})(?:\)|\s)#");

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Depart:")) . "]/following::text()[normalize-space(.)][3]", $root, true, "#(.*?) \([A-Z]{3}(?:\)|\s)#");

            // DepartureTerminal
            $itsegment['DepartureTerminal'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Depart:")) . "]/following::text()[normalize-space(.)][3]", $root, true, "#\([A-Z]{3} - (.+)\)#");

            // DepDate
            $itsegment['DepDate'] = $this->normalizeDate(implode(", ", $this->http->FindNodes(".//text()[" . $this->eq($this->t("Depart:")) . "]/following::text()[normalize-space(.)][position()=1 or position()=2]", $root)));

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode(".//node()[" . $this->eq($this->t("Arrive:")) . "]/following-sibling::node()[normalize-space(.)][last()]", $root, true, "#\(([A-Z]{3})(?:\)|\s)#");

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode(".//node()[" . $this->eq($this->t("Arrive:")) . "]/following-sibling::node()[normalize-space(.)][last()]", $root, true, "#(.*?) \([A-Z]{3}(?:\)|\s)#");

            // ArrivalTerminal
            $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrive:")) . "]/following::text()[normalize-space(.)][3]", $root, true, "#\([A-Z]{3} - (.+)\)#");

            // ArrDate
            $itsegment['ArrDate'] = $this->normalizeDate($this->re('/(.+\,\s+(\d{4}))/', implode(", ", $this->http->FindNodes(".//text()[" . $this->eq($this->t("Arrive:")) . "]/following::text()[normalize-space(.)][position()<=3]", $root))));

            // AirlineName
            $itsegment['AirlineName'] = $this->re("#^([A-Z][A-Z\d]|[A-Z\d][A-Z])\d+$#", $this->nextText($this->t("Flight:"), $root));

            // Operator
            $itsegment['Operator'] = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Operated By")) . "]", $root, true, "#{$this->t('Operated By')} (.+)#");

            // Aircraft
            $itsegment['Aircraft'] = $this->nextText($this->t("Aircraft:"), $root);

            // TraveledMiles
            $itsegment['TraveledMiles'] = $this->nextText($this->t("Flight distance:"), $root);

            // Cabin
            $itsegment['Cabin'] = $this->re("#^(.*?) \([A-Z]{1,2}\)$#", $this->nextText($this->t("Fare Class:"), $root));

            // BookingClass
            $itsegment['BookingClass'] = $this->re("#\(([A-Z]{1,2})\)$#", $this->nextText($this->t("Fare Class:"), $root));

            // Seats
            if (isset($seats[$itsegment['DepCode'] . '-' . $itsegment['ArrCode']])) {
                $itsegment['Seats'] = $seats[$itsegment['DepCode'] . '-' . $itsegment['ArrCode']];
            }

            // Duration
            $duration = $this->nextText($this->t("Flight Time:"), $root);

            if ($duration === null) {
                $duration = $this->nextText($this->t("Travel Time:"), $root);
            }
            $itsegment['Duration'] = $duration;

            // Meal
            $itsegment['Meal'] = $this->nextText($this->t("Meal:"), $root);

            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;

        return $itineraries;
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
            if (stripos($headers["subject"], $re) !== false) {
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
        $this->logger->info('Relative date: ' . date('r', $this->date));

        foreach ($this->reBody2 as $lang=> $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parseHtml(),
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

    private function normalizeDate($instr, $relDate = false)
    {
        if ($relDate === false) {
            $relDate = $this->date;
        }
        //		$this->logger->alert($instr);
//        $day = '';
//        if( preg_match('/(\+\d{1,2})\s+[a-z]+/i', $instr, $m) ){
//            $day = $m[1];
        $instr = preg_replace('/(\+\d{1,2}\s+\w+\s*\,\s*)/i', '', $instr);
//        }
        $in = [
            "#^(\d+:\d+ [ap])\.m\., [^\s\d]+\., ([^\s\d]+)\. (\d+), (\d{4})$#", //7:05 a.m., Mon., May. 21, 2018
            "#^(\d+:\d+)\s*, [^\s\d]+\., ([^\s\d]+)\. (\d+), (\d{4})$#", //7:05 a.m., Mon., May. 21, 2018
        ];
        $out = [
            "$3 $2 $4, $1m",
            "$3 $2 $4, $1",
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // fix for short febrary
        if (strpos($str, "29 February") !== false && date('m/d', strtotime(str_replace("%Y%", date('Y', $relDate), $str))) == '03/01') {
            $str = str_replace("%Y%", date('Y', $relDate) + 1, $str);
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && isset($m['week'])) {
                $str = str_replace("%Y%", date('Y', $relDate), $str);
                $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang);

                return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
            }
        }

        if (strpos($str, "%Y%") !== false) {
            return EmailDateHelper::parseDateRelative(null, $relDate, true, $str);
        }

        return strtotime($str, $relDate);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }
}
