<?php

namespace AwardWallet\Engine\icelandair\Email;

use AwardWallet\Engine\MonthTranslate;

class YourFlightTicketPdf extends \TAccountChecker
{
    public $mailFiles = "icelandair/it-1925981.eml, icelandair/it-4595428.eml, icelandair/it-6481574.eml, icelandair/it-9971959.eml";

    public $reFrom = "@icelandair.is";
    public $reSubject = [
        "en"=> "Your flight ticket:",
        "is"=> "Flugmiðinn þinn:",
    ];
    public $reBody = 'Icelandair';
    public $reBody2 = [
        "en"=> "Booking reference:",
    ];
    public $date;
    public $text;
    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    private $parser = null;

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;

        $it = [];
        $it['Kind'] = 'T';

        // RecordLocator
        $it['RecordLocator'] = $this->re("#Booking reference:\s+(\w+)#", $text);

        // TripNumber

        $pdfs = $this->parser->searchAttachmentByName('E-Ticket_.*.pdf');

        foreach ($pdfs as $pdf) {
            if (($ptext = \PDF::convertToText($this->parser->getAttachmentBody($pdf))) === null) {
                return null;
            }
            // Passengers
            $it['Passengers'][] = $this->re("#Name:\s+(.*?)\s{2,}#", $ptext);

            // TicketNumbers
            $it['TicketNumbers'][] = $this->re("#Ticket Number:\s+(.*?)\s{2,}#", $text);
        }
        $it['TicketNumbers'] = array_values(array_unique(array_filter($it['TicketNumbers'])));

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
        $it['ReservationDate'] = $this->date = strtotime($this->normalizeDate($this->re("#Date of issue:\s+(.+)#", $text)));

        // NoItineraries
        // TripCategory

        $segments = array_filter(array_map("trim", explode("\n", substr($text, strpos($text, 'Flight'), strpos($text, 'Air fare:') - strpos($text, 'Flight')))),
        function ($s) {
            return preg_match("#\s{2,}#", $s);
        });
        $head = current($segments);
        unset($segments[key($segments)]);

        $cols = array_filter(explode("|", preg_replace("#\s{2,}#", "|", $head)));
        $pos = [];

        foreach ($cols as $cn) {
            $pos[$cn] = strpos($head, $cn);
        }
        arsort($pos);

        foreach ($segments as $stext) {
            $cols = [];

            foreach ($pos as $cn=>$p) {
                $cols[$cn] = trim(substr($stext, $p));
                $stext = substr($stext, 0, $p);
            }
            $keys = [
                "Seat",
                "Arr Time",
                "Dep Time",
                "Terminal",
                "Class",
                "Date",
                "To",
                "From",
                "Flight",
            ];

            foreach ($keys as $key) {
                if (!isset($cols[$key])) {
                    $this->http->log("Undefined col: " . $key);

                    return;
                }
            }
            // print_r($cols);die();

            if (strlen($cols['Date']) < 5 && strlen($cols['To']) > 4) {
                if (preg_match("#^\s*([A-Z]{3})\s*(.+)$#", $cols['To'], $m)) {
                    $cols['To'] = $m[1];
                    $cols['Date'] = $m[2] . $cols['Date'];
                }
            }

            $date = strtotime($this->normalizeDate($cols['Date']));
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#^\w{2}(\d+)$#", $cols['Flight']);

            // DepCode
            $itsegment['DepCode'] = re("#([A-Z]{3})#", $cols['From']);

            // DepName
            // DepartureTerminal
            $itsegment['DepartureTerminal'] = $cols['Terminal'];

            // DepDate
            if ($date) {
                $itsegment['DepDate'] = strtotime($this->re("#(\d+:\d+)#", $cols['Dep Time']), $date);
            }
            // ArrCode
            $itsegment['ArrCode'] = re("#([A-Z]{3})#", $cols['To']);

            // ArrName
            // ArrivalTerminal
            // ArrDate
            if (!empty($date)) {
                $itsegment['ArrDate'] = strtotime($this->re("#(\d+:\d+)#", $cols['Arr Time']), $date);

                if (strpos($cols['Arr Time'], "+1") !== false) {
                    $itsegment['ArrDate'] = strtotime("+1 day", $itsegment['ArrDate']);
                }
            }
            // AirlineName
            $itsegment['AirlineName'] = $this->re("#^(\w{2})\d+$#", $cols['Flight']);

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            // BookingClass
            $itsegment['BookingClass'] = $cols['Class'];

            // PendingUpgradeTo
            // Seats
            $itsegment['Seats'] = $cols['Seat'];

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
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('E-Ticket_.*.pdf');

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        if (strpos($text, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($text, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->parser = $parser;
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];

        $pdfs = $parser->searchAttachmentByName('E-Ticket_.*.pdf');

        if (!isset($pdfs[0])) {
            return null;
        }
        $pdf = $pdfs[0];

        if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return null;
        }

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePdf($itineraries);

        $result = [
            'emailType'  => 'YourFlightTicketPdf' . ucfirst($this->lang),
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
        //		 $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)([^\d\s]+)$#", //26OCT
        ];
        $out = [
            "$1 $2 $year",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
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
}
