<?php

namespace AwardWallet\Engine\porter\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;

class FlightReminder2013 extends \TAccountChecker
{
    public $mailFiles = "porter/it-30677749.eml, porter/it-31120706.eml, porter/it-4158724.eml, porter/it-4225454.eml, porter/it-4252529.eml, porter/it-4321287.eml, porter/it-4374616.eml, porter/it-4394724.eml, porter/it-6405513.eml, porter/it-6405514.eml, porter/it-6449878.eml";
    public $reFrom = "@flyporter.com";
    public $reSubject = [
        "en" => "Flight Reminder - Boarding Pass Attached",
        "en2"=> "IMPORTANT - Schedule Change Notification",
        "fr" => "Rappel de vol - carte d’embarquement ci-jointe",
    ];
    public $reBody = 'flyporter.com';
    public $reBody2 = [
        "en"=> "Depart",
        "fr"=> "Départ",
    ];

    public static $dictionary = [
        "en" => [
            "Confirmation Number:" => ["Confirmation Number:", "Numéro de confirmation:", "Numéro de confirmation :"],
            "VIPorter #"           => ["VIPorter #", "VIPorter Number", "VIPorter Number / Numéro VIPorter", "VIPorter Number/Numéro VIPorter"],
            "Name"                 => ["Name", "Name / Nom"],
            "Depart"               => ["Depart", "Depart / Départ", "Depart/Départ"],
            "Flight / Seat"        => ["Flight / Seat", "Flight #/Seat #"],
            "Base Fare:"           => ["Base Fare:", "FarePrice:"],
        ],
        "fr" => [
            "Flight / Seat"        => "Vol / Siège",
            "Confirmation Number:" => "Numéro de confirmation :",
            "Name"                 => "Nom",
            "VIPorter #"           => "VIPorter #",
            "Booking Date:"        => "Réservation Date :",
            "Depart"               => "Départ",
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $seats = [];

        foreach ($this->http->FindNodes("//text()[{$this->eq($this->t("Flight / Seat"))}]/ancestor::tr[1]/following-sibling::tr/td[3][normalize-space(.)!='']") as $s) {
            preg_match_all("#(\d+)/(\d+[A-Z])#", $s, $matches, PREG_SET_ORDER);

            foreach ($matches as $m) {
                $seats[$m[1]][] = $m[2];
            }
        }

        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("(.//text()[{$this->contains($this->t('Confirmation Number:'))}])[1]/following::text()[normalize-space(.)!=''][1]");

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[{$this->eq($this->t("Name"))}]/ancestor::tr[1]/following-sibling::tr/td[1][normalize-space(.)!='']");

        // TicketNumbers
        // AccountNumbers
        $it['AccountNumbers'] = $this->http->FindNodes("//text()[{$this->eq($this->t("VIPorter #"))}]/ancestor::tr[1]/following-sibling::tr/td[2][normalize-space(.)!='']");

        // Cancelled
        // TotalCharge
        $node = $this->http->FindSingleNode('//text()[contains(normalize-space(), "Total Fare Price:")]/ancestor::td[1]/following-sibling::td[1]');
        $sum = $this->getTotalCurrency($node);

        if (!empty($sum['Total'])) {
            $it['TotalCharge'] = $sum['Total'];
            $it['Currency'] = $sum['Currency'];
            $node = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Base Fare:'))}]/ancestor::td[1]/following-sibling::td[1]");
            $sum = $this->getTotalCurrency($node);

            if (!empty($sum['Total'])) {
                $it['BaseFare'] = $sum['Total'];
            }
        }

        // BaseFare
        // Currency
        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        $it['ReservationDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Booking Date:")) . "]", null, true, "#:\s+(.+)#")));

        if (empty($it['ReservationDate'])) {
            $it['ReservationDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Booking Date:")) . "]/ancestor::td[1]/following-sibling::td[1]")));
        }
        // NoItineraries
        // TripCategory

        $xpath = "//text()[" . $this->eq($this->t("Depart")) . "]/ancestor::tr[1][./preceding::text()[normalize-space()!=''][1][not(contains(normalize-space(),'Original'))]]/following-sibling::tr[normalize-space(.)]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $xpath = "//text()[" . $this->eq($this->t("Depart")) . "]/ancestor::table[1][./preceding::text()[normalize-space()!=''][1][not(contains(normalize-space(),'Original'))]]/following-sibling::table[1]/descendant::tr[normalize-space(.)]";
            $nodes = $this->http->XPath->query($xpath);
        }

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[1]", $root)));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[2]", $root, true, "#^\d+$#");

            if ($this->http->XPath->query("//a[contains(@href,'.flyporter.com')]")->length > 0
                && $this->http->XPath->query("//text()[contains(.,'VIPorter')]")->length > 0) {
                //https://en.wikipedia.org/wiki/Porter_Airlines
                $itsegment['AirlineName'] = 'PD';
            }
            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./td[3]", $root, true, "#\(([A-Z]{3})\)#");

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[3]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#");

            // DepartureTerminal
            $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][last()]", $root, true, "#Terminal\s*(.+)#");

            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][last()]", $root, true, "#\d+:\d+(?:\s*[ap]m)?#i"), $date);

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[4]", $root, true, "#\(([A-Z]{3})\)#");

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[4]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#");

            // ArrivalTerminal
            $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)][last()]", $root, true, "#Terminal\s*(.+)#");

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)][last()]", $root, true, "#\d+:\d+(?:\s*[ap]m)?#i"), $date);

            // AirlineName
            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            // BookingClass
            // PendingUpgradeTo
            // Seats
            if (isset($seats[$itsegment['FlightNumber']])) {
                $itsegment['Seats'] = implode(", ", $seats[$itsegment['FlightNumber']]);
            }

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
        $name = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($name) . ucfirst($this->lang),
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
        // $this->http->Log('[info] '. $str);
        $in = [
            "#^(\d+\s+[^\d\s]+\s+\d{4})$#", //11 Feb 2014
            "#^(\d+)\s+([^\d\s]+)\.\s+(\d{4})$#", //26 avr. 2014
        ];
        $out = [
            "$1",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
