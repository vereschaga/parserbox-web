<?php

namespace AwardWallet\Engine\govoyages\Email;

use AwardWallet\Engine\MonthTranslate;

class ConfirmationReservation extends \TAccountChecker
{
    public $mailFiles = "govoyages/it-12185395.eml, govoyages/it-12234410.eml, govoyages/it-12234417.eml";

    public $reFrom = '@fr.govoyages.com';

    public $reSubject = [
        'GO Voyages - Confirmation de réservation No:',
        'GO Voyages - Votre résumé de voyage No:',
    ];

    public $reBody = 'www.govoyages.com';
    public $reBody2 = [
        "fr" => 'Votre itinéraire détaillé',
    ];
    public $lang = 'fr';
    public static $dict = [
        'fr' => [],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        //		$body = $parser->getHTMLBody();
        //		$this->AssignLang($body);
        $its = $this->parseEmail();

        return [
            'emailType'  => "ConfirmationReservation" . $this->lang,
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = html_entity_decode($parser->getHTMLBody());

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        return $this->AssignLang($body);
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $reSubject) {
            if (strpos($headers["subject"], $reSubject) !== false) {
                return true;
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

    protected function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T'];
        $it['RecordLocator'] = $this->http->FindSingleNode("//tr[normalize-space() = 'Code de réservation de la compagnie aérienne' or normalize-space() = 'Code de réservation de la companie aérienne']/following::tr[normalize-space()][1]/descendant::td[not(.//td)][normalize-space()][last()]", null, true, "#^\s*([A-Z\d]{5,7})\s*$#");

        if (empty($it['RecordLocator']) && empty($this->http->FindSingleNode("(//*[contains(normalize-space(), 'Code de réservation de la compagnie aérienne') or contains(normalize-space(), 'Code de réservation de la companie aérienne')])[1]"))) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }
        $it['TripNumber'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'La référence de votre dossier GO Voyages est')]/following::text()[normalize-space()][1]", null, true, "#^\s*([A-Z\d]{5,})\s*$#");

        $it['Passengers'] = array_values(array_filter($this->http->FindNodes("//text()[normalize-space() = 'NOM / Prénom']/ancestor::tr[1]/following-sibling::tr/td[3]")));

        if (empty($it['Passengers'])) {
            $it['Passengers'] = array_values(array_filter($this->http->FindNodes("//text()[normalize-space() = 'NOM , Prénom']/ancestor::tr[1]/following-sibling::tr/td[3]")));
        }
        $ticketNumbers = array_filter($this->http->FindNodes("//text()[normalize-space() = 'Billet électronique']/ancestor::tr[1]/following-sibling::tr/td[5]", null, "#([\d \|\-]+)#"));

        if (!empty($ticketNumbers)) {
            $it['TicketNumbers'] = [];

            foreach ($ticketNumbers as $key => $value) {
                $it['TicketNumbers'] = array_unique(array_map('trim', array_merge($it['TicketNumbers'], explode("|", $value))));
            }
        }

        $node = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total dossier')]/following::text()[normalize-space()][1]", null, true, "#(.+)TTC#");

        if (!empty($node)) {
            $it['TotalCharge'] = $this->amount($node);
            $it['Currency'] = $this->currency($node);
        }

        $rows = $this->http->XPath->query("//text()[normalize-space() =  'Départ']/ancestor::tr[1][contains(normalize-space(), 'Arrivée')]/following-sibling::tr[normalize-space()]");

        foreach ($rows as $root) {
            $seg = [];
            // FlightNumber
            // AirlineName
            // Operator
            $node = $this->http->FindSingleNode("./td[8]", $root);

            if (preg_match("#\b(?<AirlineName>[A-Z\d]{2})\s*(?<FlightNumber>\d{1,5})\b#", $node, $m)) {
                $seg['AirlineName'] = $m['AirlineName'];
                $seg['FlightNumber'] = $m['FlightNumber'];
                $operator = $this->http->FindSingleNode("//text()[contains(., '" . trim($node) . "') and contains(normalize-space(), 'est opéré par') and contains(normalize-space(), 'collaboration avec')]", null, true, '#opéré par (.+) en collaboration#');

                if (!empty($operator)) {
                    $seg['Operator'] = $operator;
                }
            }
            // DepDate
            // DepName
            // DepCode
            // DepartureTerminal
            $node = implode(" ", $this->http->FindNodes("./td[2]//text()[normalize-space()]", $root));

            if (preg_match("#(.+?\d+:\d+)(.+?)(?: Aéroport\s*:\s*(.+?))(?:, Terminal\s*(.+?))?$#", $node, $m)) {
                $seg['DepDate'] = strtotime($this->normalizeDate($m[1]));
                $seg['DepName'] = preg_replace("#\s*,\s*#", ', ', trim($m[2]) . '. Aéroport: ' . trim($m[3]));
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;

                if (!empty($m[4])) {
                    $seg['DepartureTerminal'] = $m[4];
                }
            }

            // ArrDate
            // ArrName
            // ArrCode
            // ArrivalTerminal
            $node = implode(" ", $this->http->FindNodes("./td[4]//text()[normalize-space()]", $root));

            if (preg_match("#(.+?\d+:\d+)(.+?)(?: Aéroport\s*:\s*(.+?))(?:, Terminal\s*(.+?))?$#", $node, $m)) {
                $seg['ArrDate'] = strtotime($this->normalizeDate($m[1]));
                $seg['ArrName'] = preg_replace("#\s*,\s*#", ', ', trim($m[2]) . '. Aéroport: ' . trim($m[3]));
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

                if (!empty($m[4])) {
                    $seg['ArrivalTerminal'] = $m[4];
                }
            }

            // Aircraft
            // TraveledMiles
            // Cabin
            $col = count($this->http->FindNodes("./preceding-sibling::tr[normalize-space()][last()]/td[normalize-space()='Classe']/preceding-sibling::td", $root));

            if ($col > 1) {
                $seg['Cabin'] = $this->http->FindSingleNode("./td[" . ($col + 1) . "]", $root);
            }

            // BookingClass
            // PendingUpgradeTo
            // Seats
            // Duration
            $col = count($this->http->FindNodes("./preceding-sibling::tr[normalize-space()][last()]/td[normalize-space()='Durée']/preceding-sibling::td", $root));

            if ($col > 1) {
                $seg['Duration'] = $this->http->FindSingleNode("./td[" . ($col + 1) . "]", $root);
            }

            // Meal
            // Smoking
            // Stops
            // Gate
            // ArrivalGate
            // BaggageClaim
            $it['TripSegments'][] = $seg;
        }

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
        foreach ($this->reBody2 as $re) {
            if (stripos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\. ]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        $in = [
            "#^\s*[^\d\s]+\s+(\d{1,2})\s+([^\d\s]+)\s+(\d{4}),\s*(\d+:\d+)$#", //Samedi 25 Juin 2016, 09:45
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }
}
