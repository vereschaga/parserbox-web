<?php

namespace AwardWallet\Engine\tapportugal\Email;

class ReceiptPdf extends \TAccountChecker
{
    public $mailFiles = "tapportugal/it-7779775.eml, tapportugal/it-7828103.eml";
    public $reFrom = "recibos@tap.pt";
    public $reSubject = [
        "en" => ["Ticket nbr"],
    ];
    public $reBody = [
        'en' => 'Passenger Name',
    ];

    public $pdfPattern = "TAP receipt.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $text;
    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;

        $table = mb_substr($text,
            $sp = $this->pos($text, $this->t("Method of"), 0, true),
            $this->pos($text, $this->t("Total Value"), 0) - $sp, 'UTF-8');

        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = CONFNO_UNKNOWN;

        // TripNumber
        // Passengers
        if (preg_match_all("#^\s{0,10}(\S[A-Z\s]+)\s{5,}#mU", $table, $m)) {
            $it['Passengers'] = implode(" ", $m[1]);
        }

        // TicketNumbers
        if (preg_match_all("#^\s{0,10}.*\s{3}(\d{6,20})#m", $table, $m)) {
            $it['TicketNumbers'] = $m[1];
        }

        // AccountNumbers
        // Cancelled
        // TotalCharge
        // Currency
        if (preg_match("#" . $this->t("Total Value:") . "\s+([A-Z]{3})\s+([\d,.]+)#", $text, $m)) {
            $it['Currency'] = $m[1];
            $it['TotalCharge'] = (float) str_replace(',', '', $m[2]);
        }

        // BaseFare
        $it['BaseFare'] = 0.0;

        foreach ($it['TicketNumbers'] as $key => $Ticket) {
            if (preg_match("#" . $Ticket . ".+\s+([\d.,]+)\s+[\d.,]+$#mU", $table, $m)) {
                $it['BaseFare'] += (float) str_replace(',', '', $m[1]);
            }
        }

        // Tax
        $t = explode("\n", $table);

        foreach ($t as $key => $value) {
            if (($pos = strpos($value, 'Itinerary')) !== false) {
                break;
            }
        }

        // Fees
        $rates = [];

        foreach ($t as $key => $value) {
            if (substr($value, $pos) !== false && preg_match("#([A-Z*]{2})\s+[A-Z]{3}\s+([\d,.]+)(?:\s|$)#", substr($value, $pos), $m)) {
                if (isset($rates[$m[1]])) {
                    $rates[$m[1]] += (float) str_replace(",", '', $m[2]);
                } else {
                    $rates[$m[1]] = (float) str_replace(",", '', $m[2]);
                }
            }
        }
        $blockFees = substr($text, strpos($text, 'Description of Fees:'));

        $ratesName = [];

        if (preg_match_all("#^\s{0,20}([A-Z*]{2})\s+(.+)(?:$|\s{5,})#Um", $blockFees, $m)) {
            foreach ($m[0] as $key => $value) {
                $ratesName[$m[1][$key]] = $m[2][$key];
            }
        }

        foreach ($rates as $key => $value) {
            $it['Fees'][] = ["Name" => $ratesName[$key], "Charge" => $value];
        }
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        if (preg_match_all("#\s{3,}([A-Z\d]{2})(\d{1,5})\s+([A-Z]{3})\s+([A-Z]{3})\s*(\d{1,2}\/\d{2}\/\d{4}\s*\d{1,2}:\d{2})#", $table, $m)) {
            foreach ($m[0] as $key => $flight) {
                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $m[2][$key];

                // DepCode
                $itsegment['DepCode'] = $m[3][$key];

                // DepName
                // DepartureTerminal

                // DepDate
                $itsegment['DepDate'] = strtotime(str_replace("/", ".", $m[5][$key]));

                // ArrCode
                $itsegment['ArrCode'] = $m[4][$key];

                // ArrName
                // ArrivalTerminal
                // ArrDate
                $itsegment['ArrDate'] = MISSING_DATE;

                // AirlineName
                $itsegment['AirlineName'] = $m[1][$key];

                // Operator
                // Aircraft
                // TraveledMiles
                // AwardMiles
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

        foreach ($this->reSubject as $reSubject) {
            foreach ($reSubject as $re) {
                if (stripos($headers["subject"], $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);
        $text = '';

        foreach ($pdfs as $pdf) {
            $text .= \PDF::convertToText($parser->getAttachmentBody($pdf));
        }

        foreach ($this->reBody as $re) {
            if (stripos($text, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = false;
        $itineraries = [];

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                return null;
            }

            foreach ($this->reBody as $lang => $re) {
                if (strpos($this->text, $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
            $this->parsePdf($itineraries);
        }
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

    private function pos($text, $match, $s = 0, $byend = false)
    {
        $match = (array) $match;

        foreach ($match as $m) {
            if (($pos = mb_strpos($text, $m, $s, 'UTF-8')) !== false) {
                if ($byend) {
                    $pos = $pos + mb_strlen($m, 'UTF-8');
                }

                return $pos;
            }
        }

        return false;
    }
}
