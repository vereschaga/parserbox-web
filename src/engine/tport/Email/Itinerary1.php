<?php

//TODO: maybe should merge with ItineraryDetaild.php

namespace AwardWallet\Engine\tport\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "tport/it-1849390.eml, tport/it-3495413.eml, tport/it-6752864.eml, tport/it-9932442.eml";

    public $reFrom = "travelport.com";
    public $reBody = [
        'en' => ['Worldspan Reservation', 'Confirmation Number'],
        'nl' => ['Worldspan Reserveringsnummer', 'Bevestiging'],
    ];
    public $reSubject = [
        'Itinerary for',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [],
        'nl' => [
            'Travel Plans for' => 'Reisplannen voor',
            //			'Frequent Flyer' => '',
            //			'Worldspan Reservation ID',
            'Depart'              => 'Vertrek',
            'Confirmation Number' => 'Bevestiging',
            'Flight'              => 'Vlucht',
            //			'Flight Number',
            'Class'    => 'Klasse',
            'Arrive'   => 'Aankomst',
            'Seat'     => 'Stoel',
            'Meal'     => 'Maaltijd',
            'Aircraft' => 'Toestel',
            //			'Stopovers' => '',
            'Mileage'     => 'Afstand',
            'Travel Time' => 'Reistijd',
        ],
    ];
    private $date;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());

        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'Itinerary1' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(translate(.,'TRAVELPORT','travelport'),'travelport')]")->length > 0) {
            $body = $parser->getHTMLBody();

            return $this->AssignLang($body);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail()
    {
        $pax = array_filter($this->http->FindNodes("//text()[contains(., '{$this->t('Travel Plans for')}')]/ancestor::*[1]/following-sibling::*"));

        if (count($pax) === 0) {
            $pax = array_filter($this->http->FindNodes("//text()[contains(., '{$this->t('Travel Plans for')}')]", null, "#{$this->t('Travel Plans for')}\s+(.+)#"));
        }
        $newPax = [];
        array_walk($pax, function ($el) use (&$newPax) {
            if (stripos($el, ',') !== false) {
                $newPax = array_merge($newPax, explode(',', $el));
            } else {
                $newPax[] = $el;
            }
        });
        $pax = array_map("trim", $newPax);
        $ff = array_values(array_unique(array_filter($this->http->FindNodes("//text()[contains(normalize-space(.), 'Frequent Flyer')]/ancestor::td[1]/following-sibling::td[1]", null, "#(A-Z\d)+$#"))));
        $tripNum = $this->http->FindSingleNode("//text()[contains(., 'Worldspan Reservation ID')]/ancestor::*[1]/following-sibling::*[1]", null, true, "#[A-Z\d]{5,}#");

        if (empty($tripNum)) {
            $tripNum = $this->http->FindSingleNode("//text()[contains(., 'Worldspan Reservation ID')]", null, true, "#Worldspan Reservation ID\s+([A-Z\d]{5,})#");
        }

        $xpath = "//text()[contains(.,'{$this->t('Depart')}')]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $node) {
            $rl = $this->http->FindSingleNode("./descendant::td[contains(., '{$this->t('Confirmation Number')}')]/following-sibling::td[1]", $node, true, "#[A-Z\d]{5,}#");
            $airs[$rl][] = $node;
        }
        $its = [];

        foreach ($airs as $rl => $roots) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $rl;
            $it['TripNumber'] = $tripNum;
            $it['Passengers'] = $pax;

            if (count($ff) > 0) {
                $it['AccountNumbers'] = $ff;
            }

            foreach ($roots as $root) {
                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding::tr[contains(., '{$this->t('Flight')}')][1]/td[2]", $root)));

                if ($date) {
                    $this->date = $date;
                }
                $seg = [];
                $node = $this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(.), '{$this->t('Flight Number')}')]/following-sibling::td[1]", $root);

                if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }
                $seg['Cabin'] = $this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(.), '{$this->t('Class')}')]/following-sibling::td[1]", $root);
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                $node = implode("\n", $this->http->FindNodes("./descendant::td[contains(., '{$this->t('Depart')}')]/ancestor::tr[1]/preceding-sibling::tr[1]/following-sibling::tr[position()<=4]/td[2][normalize-space(.)!='']", $root));

                if (preg_match('/(.+)\s*-\s*([A-Z]{3})/', $node, $m)) {
                    $seg['DepName'] = $m[1];
                    $seg['DepCode'] = $m[2];
                } else {
                    $seg['DepName'] = $this->re("#^(.+)#", $node);
                }
                $seg['DepartureTerminal'] = $this->re("#(Terminal.+)#", $node);
                $seg['DepDate'] = strtotime($this->normalizeDate($this->re("#(.+)$#", $node)));

                $node = implode("\n", $this->http->FindNodes("./descendant::td[contains(., '{$this->t('Arrive')}')]/ancestor::tr[1]/preceding-sibling::tr[1]/following-sibling::tr[position()<=4]/td[2][normalize-space(.)!='']", $root));

                if (preg_match('/(.+)\s*-\s*([A-Z]{3})/', $node, $m)) {
                    $seg['ArrName'] = $m[1];
                    $seg['ArrCode'] = $m[2];
                } else {
                    $seg['ArrName'] = $this->re("#^(.+)#", $node);
                }
                $seg['ArrivalTerminal'] = $this->re("#(Terminal.+)#", $node);
                $seg['ArrDate'] = strtotime($this->normalizeDate($this->re("#(.+)$#", $node)));

                $seg['Seats'] = str_replace("-", "", $this->http->FindSingleNode("./descendant::td[contains(., '{$this->t('Seat')}')]/following-sibling::td[1]", $root));
                $seg['Meal'] = $this->http->FindSingleNode("./descendant::td[contains(., '{$this->t('Meal')}')]/following-sibling::td[1]", $root);
                $seg['Aircraft'] = $this->http->FindSingleNode("./descendant::td[contains(., '{$this->t('Aircraft')}')]/following-sibling::td[1]", $root);
                $seg['Stops'] = $this->http->FindSingleNode("./descendant::td[contains(., '{$this->t('Stopovers')}')]/following-sibling::td[1]", $root);
                $seg['TraveledMiles'] = $this->http->FindSingleNode("./descendant::td[contains(., '{$this->t('Mileage')}')]/following-sibling::td[1]", $root);
                $seg['Duration'] = $this->http->FindSingleNode("./descendant::td[contains(., '{$this->t('Travel Time')}')]/following-sibling::td[1]", $root);

                $it['TripSegments'][] = $seg;
            }
            $its[] = $it;
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //Saturday, June 18, 2016 
            '#^\s*\S+\s+(\w+)\s+(\d+),\s+(\d+)\s*$#i',
            //03:55 PM , Sunday, August 17
            '#^\s*(\d+:\d+\s*(?:[ap]m)?)\s*,\s*\S+\s+(\w+)\s+(\d+)\s*$#i',
        ];
        $out = [
            '$2 $1 $3',
            '$3 $2 ' . $year . ' $1',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

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

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
