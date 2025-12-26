<?php

namespace AwardWallet\Engine\volotea\Email;

use AwardWallet\Engine\MonthTranslate;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "volotea/it-6380015.eml, volotea/it-6414693.eml";

    public $reFrom = "volotea.com";
    public $reSubject = [
        "en"=> "Volotea",
    ];
    public $reBody = 'Volotea';
    public $reBody2 = [
        "en"=> "BOARDING PASS",
        "it"=> "CARTA DI IMBARCO",
    ];

    public static $dictionary = [
        "en" => [],
        "it" => [
            "CONF. NUMBER:"=> "N.RIFERIMENTO:",
            "BOARDING PASS"=> "CARTA DI IMBARCO",
            "DATE:"        => "DATA:",
            "FLIGHT N.:"   => "N.VOLO:",
            "ORIGIN:"      => "DESTINAZIONE:",
            "DEPARTURE:"   => "PARTENZA:",
            "DESTINATION:" => "ORIGINE:",
            "ARRIVAL:"     => "PARTENZA:",
            "SEAT:"        => "POSTO:",
        ],
    ];
    public $lang = "en";
    /** @var \HttpBrowser */
    private $pdf;

    public function parsePdf(&$itineraries)
    {
        $text = $this->pdf->Response["body"];
        // echo $text;
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#" . $this->t("CONF. NUMBER:") . "\n(\w+)#", $text);

        // TripNumber
        // Passengers
        $it['Passengers'] = preg_match_all("#" . $this->t("BOARDING PASS") . "\n(.*?)\n#", $text, $m) ? $m[1] : [];

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

        $date = strtotime($this->normalizeDate($this->re("#" . $this->t("DATE:") . "\n(.+)#", $text)));

        $itsegment = [];
        // FlightNumber
        $itsegment['FlightNumber'] = $this->re("#" . $this->t("FLIGHT N.:") . "\n\w{2}\s+(\d+)#", $text);

        // DepCode
        $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

        // DepName
        $itsegment['DepName'] = $this->re("#" . $this->t("ORIGIN:") . "\n(.+)#", $text);

        // DepartureTerminal
        // DepDate
        $itsegment['DepDate'] = strtotime($this->re("#" . $this->t("DEPARTURE:") . "\n(.+)#", $text), $date);

        // ArrCode
        $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

        // ArrName
        $itsegment['ArrName'] = $this->re("#" . $this->t("DESTINATION:") . "\n(.+)#", $text);

        // ArrivalTerminal
        // ArrDate
        $itsegment['ArrDate'] = strtotime($this->re("#" . $this->t("ARRIVAL:") . "\n(.+)#", $text), $date);

        // AirlineName
        $itsegment['AirlineName'] = $this->re("#" . $this->t("FLIGHT N.:") . "\n(\w{2})\s+\d+#", $text);

        // Operator
        // Aircraft
        // TraveledMiles
        // AwardMiles
        // Cabin
        // BookingClass
        // PendingUpgradeTo
        // Seats
        $itsegment['Seats'] = $this->re("#" . $this->t("SEAT:") . "\n(.+)#", $text);

        // Duration
        // Meal
        // Smoking
        // Stops
        $it['TripSegments'][] = $itsegment;

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('volotea.*pdf');

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

        if (!$this->sortedPdf($parser)) {
            return null;
        }

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->pdf->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePdf($itineraries);

        $class = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($class) . ucfirst($this->lang),
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
        $in = [
            "#^(\d+\s+[^\d\s]+\s+\d{4})$#", //26 GIU 2014
        ];
        $out = [
            "$1",
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

    private function sortedPdf(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('volotea.*pdf');

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) === null) {
            return false;
        }
        $this->pdf = clone $this->http;
        $this->pdf->SetBody($html);
        $res = "";

        $pages = $this->pdf->XPath->query("//div[starts-with(@id, 'page')]");

        foreach ($pages as $page) {
            $nodes = $this->pdf->XPath->query(".//p", $page);

            $cols = [];
            $grid = [];

            foreach ($nodes as $node) {
                $text = implode("\n", $this->pdf->FindNodes("./descendant::text()[normalize-space(.)]", $node));
                $top = $this->pdf->FindSingleNode("./@style", $node, true, "#top:(\d+)px;#");
                $left = $this->pdf->FindSingleNode("./@style", $node, true, "#left:(\d+)px;#");
                $grid[$top][$left] = $text;
            }

            foreach ($grid as $row=>&$c) {
                ksort($c);
                $r = "";

                foreach ($c as $t) {
                    $r .= $t . "\n";
                }
                $c = $r;
            }

            ksort($grid);

            foreach ($grid as $r) {
                $res .= $r;
            }
        }
        $this->pdf->setBody($res);

        return true;
    }
}
