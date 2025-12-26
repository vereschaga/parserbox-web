<?php

namespace AwardWallet\Engine\hoggrob\Email;

// parsers with similar formats: amadeus/It1824289(array), amadeus/It1977890(array), amadeus/MyTripItinerary(array)

class It6083284 extends \TAccountChecker
{
    public $mailFiles = "hoggrob/it-2748716.eml, hoggrob/it-2765115.eml, hoggrob/it-2765890.eml, hoggrob/it-2782125.eml, hoggrob/it-6083284.eml, hoggrob/it-27492676.eml";
    public $reFrom = "@hrgworldwide.com";
    public $reSubject = [
        "en"=> "My Trip itinerary",
        "de"=> "Mein Reiseplan",
    ];
    public $reBody = 'rgworldwide.com';
    public $reBody2 = [
        "en"=> "Trip Name:",
        "de"=> "Name der Reise:",
    ];

    public static $dictionary = [
        "en" => [],
        "de" => [
            "Trip status:"        => "Reisestatus:",
            "Confirmation Number:"=> "Bestätigungsnummer:",
            "Seat selection:"     => "Sitzplatzauswahl:",
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $status = $this->http->FindSingleNode("//td[not(.//td) and starts-with(normalize-space(.),'{$this->t("Trip status:")}')]", null, true, "/{$this->t("Trip status:")}\s*(.+)/");

        $xpath = "//*[contains(@class, 'colLocation')]/..";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $xpath = "//img[contains(@src, 'images/IcoFlight1.gif')]/following::table[1]/descendant::tr[1]/../tr[./td[4]]";
            $nodes = $this->http->XPath->query($xpath);
        }

        if ($nodes->length == 0) {
            $xpath = "//img[contains(@src, 'images/IcoFlight1.gif')]/following::table[2]/descendant::tr[1]/../tr[./td[4]]";
            $nodes = $this->http->XPath->query($xpath);
        }
        $airs = [];

        foreach ($nodes as $root) {
            if (!$rl = $this->http->FindSingleNode("./../preceding::table[1]", $root, true, "#" . $this->t("Confirmation Number:") . "\s*(\w+)#")) {
                if (!$rl = $this->http->FindSingleNode("//*[@class='summaryRecLoc']")) {
                    $rl = $this->http->FindSingleNode("//img[contains(@src, 'images/IcoFlight1.gif')]/preceding::text()[normalize-space(.)][1]", null, true, "#\((\w+)\)#");
                }
            }
            $airs[$rl][] = $root;
        }

        foreach ($airs as $rl=>$roots) {
            $it = [];
            $it['Kind'] = "T";

            // Status
            if ($status) {
                $it['Status'] = $status;
            }

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // Passengers
            $it['Passengers'] = $this->http->FindNodes("//*[@class='travellerNameList']");
            $it['Passengers'] = array_filter([$this->http->FindSingleNode("(//img[contains(@src, 'images/IcoFlight1.gif')])[1]/preceding::b[1]")]);

            // TotalCharge
            if (!$it['TotalCharge'] = $this->amount($this->re("#^([\d\,\.]+)\s+[A-Z]{3}#", $this->http->FindSingleNode("//*[@class='price']")))) {
                $it['TotalCharge'] = $this->amount($this->re("#^([\d\,\.]+)\s+[A-Z]{3}#", $this->http->FindSingleNode("./preceding::img[contains(@src, 'images/IcoFlight1.gif')][1]/following::text()[normalize-space(.)][1]", $root)));
            }

            // Currency
            if (!$it['Currency'] = $this->re("#^[\d\,\.]+\s+([A-Z]{3})$#", $this->http->FindSingleNode("//*[@class='price']"))) {
                $it['Currency'] = $this->re("#^[\d\,\.]+\s+([A-Z]{3})$#", $this->http->FindSingleNode("./preceding::img[contains(@src, 'images/IcoFlight1.gif')][1]/following::text()[normalize-space(.)][1]", $root));
            }

            foreach ($roots as $root) {
                $itsegment = [];

                // FlightNumber
                if (!$itsegment['FlightNumber'] = $this->http->FindSingleNode(".//*[@class='flight']", $root, true, "#\d+$#")) {
                    $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][1]", $root, true, "#\d+$#");
                }

                // DepCode
                if (!$itsegment['DepCode'] = $this->http->FindSingleNode("./td[1]//*[@class='iataStateCode']", $root, true, "#\(([A-Z]{3})\)#")) {
                    $itsegment['DepCode'] = $this->re("#\(([A-Z]{3})\)#", implode(", ", $this->http->FindNodes("./td[1]/descendant::text()[normalize-space(.)][not(position()=last() or position()=last()-1)]", $root)));
                }

                // DepName
                if (!$itsegment['DepName'] = implode(", ", $this->http->FindNodes("./td[1]//*[@class='cityName' or @class='airportNameContainer']", $root))) {
                    $itsegment['DepName'] = preg_replace("#(, )?(?:\([A-Z]{3}\)|Terminal\s+\w+)#", "", implode(", ", $this->http->FindNodes("./td[1]/descendant::text()[normalize-space(.)][not(position()=last() or position()=last()-1)]", $root)));
                }

                // DepartureTerminal
                if (!$itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./td[1]//*[@class='terminal']", $root, true, "#Terminal\s+(\w+)#")) {
                    $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][last()-2]", $root, true, "#Terminal\s+(\w+)#");
                }

                // DepDate
                if (!$date = implode(", ", $this->http->FindNodes("./td[1]//*[@class='date' or @class='time']", $root))) {
                    $date = implode(", ", $this->http->FindNodes("./td[1]/descendant::text()[normalize-space(.)][position()=last() or position()=last()-1]", $root));
                }
                $itsegment['DepDate'] = strtotime($this->normalizeDate($date));

                // ArrCode
                if (!$itsegment['ArrCode'] = $this->http->FindSingleNode("./td[2]//*[@class='iataStateCode']", $root, true, "#\(([A-Z]{3})\)#")) {
                    $itsegment['ArrCode'] = $this->re("#\(([A-Z]{3})\)#", implode(", ", $this->http->FindNodes("./td[2]/descendant::text()[normalize-space(.)][not(position()=last() or position()=last()-1)]", $root)));
                }

                // ArrName
                if (!$itsegment['ArrName'] = implode(", ", $this->http->FindNodes("./td[2]//*[@class='cityName' or @class='airportNameContainer']", $root))) {
                    $itsegment['ArrName'] = preg_replace("#(, )?(?:\([A-Z]{3}\)|Terminal\s+\w+|\d+:\d+)#", "", implode(", ", $this->http->FindNodes("./td[2]/descendant::text()[normalize-space(.)][not(position()=last() or position()=last()-1)]", $root)));
                }

                // ArrivalTerminal
                if (!$itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./td[2]//*[@class='terminal']", $root, true, "#Terminal\s+(\w+)#")) {
                    $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][last()-2]", $root, true, "#Terminal\s+(\w+)#");
                }

                // ArrDate
                if (!$date = implode(", ", $this->http->FindNodes("./td[2]//*[@class='date' or @class='time']", $root))) {
                    $date = implode(", ", $this->http->FindNodes("./td[2]/descendant::text()[normalize-space(.)][position()=last() or position()=last()-1]", $root));
                }

                if (strpos($date, ":") === false) {
                    $date = implode(", ", $this->http->FindNodes("./td[2]/descendant::text()[normalize-space(.)][position()=last() or position()=last()-2]", $root));
                }
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($date));

                // AirlineName
                if (!$itsegment['AirlineName'] = $this->http->FindSingleNode(".//*[@class='flight']", $root, true, "#(.*?)\s+\d+$#")) {
                    $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][1]", $root, true, "#(.*?)\s+\d+$#");
                }

                // Operator
                $operator = $this->http->FindSingleNode("./td[3]/descendant::text()[contains(normalize-space(.),'{$this->t('Operated by')}')]", $root, null, "/{$this->t('Operated by')}\s*(.+)\s*$/");

                if ($operator) {
                    $itsegment['Operator'] = $operator;
                }

                // Cabin
                $itsegment['Cabin'] = $this->http->FindSingleNode("./td[4]", $root, null, "/^(\w+)/u");

                // Seats
                $seat = $this->http->FindSingleNode("./td[4]/descendant::text()[contains(normalize-space(.),'{$this->t('Seat selection:')}')]", $root, null, "/{$this->t('Seat selection:')}\s*(\d{1,5}[A-Z])\s*$/");

                if ($seat) {
                    $itsegment['Seats'] = [$seat];
                }

                // Duration
                if (!$itsegment['Duration'] = $this->http->FindSingleNode(".//*[@class='durationText']", $root)) {
                    $itsegment['Duration'] = $this->http->FindSingleNode("./td[5]/descendant::text()[normalize-space(.)][1]", $root);
                }

                $it['TripSegments'][] = $itsegment;
            }
            $itineraries[] = $it;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->reFrom) === false) {
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

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'MyTripItinerary' . ucfirst($this->lang),
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
            // 08:50, Saturday, 5 November 2016    |    15:10, Freitag, 22. Mai 2015
            "/^(\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)(?:\s*[+]\s*\d+\s*[^\d\W]+)?\s*,\s*[^\d\W]{2,}\s*,\s*(\d{1,2})\.?\s+([^\d\W]{3,})\s+(\d{4})$/u",
            // 6:20 AM, Monday, December 12, 2016    |    8:50 AM + 1 day, Saturday, February 18, 2017
            "/^(\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)(?:\s*[+]\s*\d+\s*[^\d\W]+)?\s*,\s*[^\d\W]{2,}\s*,\s*([^\d\W]{3,})\s+(\d{1,2})\s*,\s*(\d{4})$/u",
        ];
        $out = [
            "$2 $3 $4, $1",
            "$3 $2 $4, $1",
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
}
