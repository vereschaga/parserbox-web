<?php

namespace AwardWallet\Engine\gulfair\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "gulfair/it-5079155.eml";

    public $reSubject = [
        'Gulf Air Reservation',
    ];
    public $date;
    public $lang = 'en';
    public static $dict = [
        'en' => [
            'BODY'             => 'Reservation code',
            'Reservation code' => 'Reservation code',
            'Itinerary'        => 'Itinerary',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "Reservation",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'www.gulfair.com')]")->length > 0) {
            $text = $parser->getHTMLBody();

            foreach (self::$dict as $lang => $reBody) {
                if (stripos($text, $reBody['BODY']) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['subject']) && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers['subject'], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "gulfair.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(),'" . $this->t('Reservation code') . "')]", null, true, "#\s+([A-Z\d]+)$#");

        $it['Passengers'] = $this->http->FindNodes("//*[contains(text(),'" . $this->t('Reservation code') . "')]/ancestor::table[1]//tr[not(contains(.,'" . $this->t('Itinerary') . "')) and not(contains(.,'" . $this->t('Reservation code') . "')) and string-length(normalize-space(.))>3]");

        $Flights = $this->http->XPath->query("//*[contains(text(),'" . $this->t('Flights') . ":')]/ancestor::table[1]");

        foreach ($Flights as $root) {
            $seg = [];
            $dateFly = $this->http->FindSingleNode("./descendant::tr[1]", $root, true, "#\S+,\s+\S+\s*\d+#");
            $year = date('Y', $this->date);

            $node = $this->http->FindSingleNode("./descendant::td[contains(.,'" . $this->t('Flights') . ":')]", $root);

            if (preg_match("#:\s*(.+?),?\s*([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                $seg['Operator'] = $m[1];
                $seg['AirlineName'] = $m[2];
                $seg['FlightNumber'] = $m[3];
            }
            $node = $this->http->FindSingleNode("./descendant::td[contains(.,'" . $this->t('From') . ":')]", $root);

            if (preg_match("#:\s*(.+?)\s*\(([A-Z]{3})\)#", $node, $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepCode'] = $m[2];
            }
            $node = $this->http->FindSingleNode("./descendant::td[contains(.,'" . $this->t('Departs') . ":')]", $root, true, "#:\s*(\d+:\d+)#");
            $seg['DepDate'] = strtotime($dateFly . ' ' . $year . ' ' . $node);

            if ($seg['DepDate'] < $this->date) {
                $seg['DepDate'] = strtotime("+1 year", $seg['DepDate']);
                $year++;
            }
            $node = $this->http->FindSingleNode("./descendant::td[contains(.,'" . $this->t('To') . ":')]", $root);

            if (preg_match("#:\s*(.+?)\s*\(([A-Z]{3})\)#", $node, $m)) {
                $seg['ArrName'] = $m[1];
                $seg['ArrCode'] = $m[2];
            }
            $node = $this->http->FindSingleNode("./descendant::td[contains(.,'" . $this->t('Arrives') . ":')]", $root, true, "#:\s*(\d+:\d+)#");
            $seg['ArrDate'] = strtotime($dateFly . ' ' . $year . ' ' . $node);

            $seg['DepartureTerminal'] = $this->http->FindSingleNode("./descendant::tr[contains(.,'" . $this->t('Departure Terminal') . ":')]", $root, true, "#:\s+(.+)#");
            $seg['ArrivalTerminal'] = $this->http->FindSingleNode("./descendant::tr[contains(.,'" . $this->t('Arrival Terminal') . ":')]", $root, true, "#:\s+(.+)#");
            $seg['Cabin'] = $this->http->FindSingleNode("./descendant::tr[contains(.,'" . $this->t('Class') . ":')]", $root, true, "#:\s+(.+)#");
            $seg['Seats'] = implode(',', array_filter($this->http->FindNodes("./descendant::tr[contains(.,'" . $this->t('Seat(s)') . "')]//td[count(descendant::td)=0]", $root, "#\s+-\s+(\d+\w)#")));

            $it['Status'] = $this->http->FindSingleNode("./descendant::td[contains(.,'" . $this->t('Status') . ":')]", $root, true, "#:\s*(.+)#");
            $seg['Meal'] = $this->http->FindSingleNode("./descendant::td[contains(.,'" . $this->t('Meal') . ":')]", $root, true, "#:\s*(.+)#");
            $seg['Aircraft'] = $this->http->FindSingleNode("./descendant::td[contains(.,'" . $this->t('Aircraft') . ":')]", $root, true, "#:\s*(.+)#");
            $seg['Duration'] = $this->http->FindSingleNode("./descendant::td[contains(.,'" . $this->t('Duration') . ":')]", $root, true, "#:\s*(.+)#");
            $seg['TraveledMiles'] = $this->http->FindSingleNode("./descendant::td[contains(.,'" . $this->t('Distance') . "')]", $root, true, "#:\s*(.+)#");
            $node = trim($this->http->FindSingleNode("./descendant::td[contains(.,'" . $this->t('Smoking') . ":')]", $root, true, "#:\s*(.+)#"));

            switch ($node) {
                case 'No':
                    $seg['Smoking'] = false;

                    break;

                case 'Yes':
                    $seg['Smoking'] = true;

                    break;
            }

            $seg = array_filter($seg);
            $it['TripSegments'][] = $seg;
        }

        $it = array_filter($it);

        return [$it];
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
            foreach (self::$dict as $lang => $reBody) {
                if (stripos($text, $reBody['Reservation code']) !== false || stripos($text, $reBody['Itinerary']) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        return true;
    }
}
