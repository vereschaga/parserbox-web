<?php

namespace AwardWallet\Engine\hoggrob\Email;

use AwardWallet\Engine\MonthTranslate;

class TicketPDF extends \TAccountChecker
{
    public $mailFiles = "hoggrob/it-6453656.eml";

    public $reFrom = "@hrgworldwide.com";
    public $reSubject = [
        "it"=> "Acquisto Trenitalia Ticketless da Agenzia HOGG ROBINSO",
    ];
    public $reBody = 'HOGG ROBINSO';
    public $reBody2 = [
        "it"=> "partenza",
    ];

    public static $dictionary = [
        "it" => [],
    ];

    public $lang = "it";

    private $date = null;

    private $text = '';

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;

        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#PNR:\s+(\w+)#", $text);

        // TripNumber
        // Passengers
        $it['Passengers'] = array_filter([$this->re("#Intestatario biglietto:\s+([^\n]+)#", $text)]);

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
        $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

        if (!preg_match("#(?<DepDate>\d+/\d+/\d{4})\s+(?<DepTime>\d+:\d+)\s+(?<DepName>.*?)\s{2,}(?<ArrName>.*?)\s{2,}(?<ArrDate>\d+/\d+/\d{4})\s{2,}(?<ArrTime>\d+:\d+)\s+#", $text, $m)) {
            $this->http->Log("General not matched");

            return;
        }
        $itsegment = [];
        $itsegment['DepName'] = $m['DepName'];
        $itsegment['ArrName'] = $m['ArrName'];
        $itsegment['DepDate'] = strtotime($this->normalizeDate($m['DepDate'] . ', ' . $m['DepTime']));
        $itsegment['ArrDate'] = strtotime($this->normalizeDate($m['ArrDate'] . ', ' . $m['ArrTime']));

        if (!preg_match("#Posti\(Seat\)[\s\n]+(?<Type>.*?\s+\d+)\s{2,}\d+\s{2,}(?<Seats>\d+\w)#", $text, $m)) {
            $this->http->Log("Second not matched");

            return;
        }
        $itsegment['Type'] = $m['Type'];
        $itsegment['Seats'] = $m['Seats'];

        $itsegment['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
        $itsegment['DepCode'] = $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

        $it['TripSegments'][] = $itsegment;
        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('ricevuta.pdf');

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
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];

        $pdfs = $parser->searchAttachmentByName('ricevuta.pdf');

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
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
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
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)/(\d+)/(\d{4}),\s+(\d+:\d+)$#",
        ];
        $out = [
            "$1.$2.$3 $4",
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
