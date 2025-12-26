<?php

namespace AwardWallet\Engine\norwegian\Email;

class It4380082 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "norwegian/it-11147745.eml, norwegian/it-11156044.eml, norwegian/it-11210972.eml, norwegian/it-11215445.eml, norwegian/it-11305353.eml, norwegian/it-4241205.eml, norwegian/it-4380082.eml, norwegian/it-4403146.eml, norwegian/it-4423865.eml";

    public $reFrom = "noreply@norwegian.";
    public $reSubject = [
        "es"=> "Recibo de viaje",
        "no"=> "Reisekvittering",
        "en"=> "Travel Receipt",
        "da"=> "Rejsekvittering",
        "fi"=> "Matkakuitti",
        "it"=> "Ricevuta di viaggio",
    ];
    public $reBody = 'Norwegian Air';
    public $reBody2 = [
        "es"=> "Info de vuelos",
        "no"=> "Flight informasjon",
        "en"=> "Flight info",
        "da"=> "Fly information",
        "fi"=> "Lennon tiedot",
        "it"=> "Info volo",
    ];

    public static $dictionary = [
        "es" => [
            //			"(Cancelled)" => "",
        ],
        "no" => [
            "Número de reserva:"=> "Bookingreferanse:",
            "Info de vuelos"    => "Flight informasjon",
            "Recibo de viaje"   => "Reisekvittering",
            "Precio total"      => "Totalbeløp",
            "Total"             => "Total",
            "(Cancelled)"       => "(Kansellert)",
        ],
        "en" => [
            "Número de reserva:"=> "Booking reference:",
            "Info de vuelos"    => "Flight info",
            "Recibo de viaje"   => "Travel Receipt",
            "Precio total"      => "Total Amount",
            "Total"             => "Total",
            "(Cancelled)"       => "(Cancelled)",
        ],
        "da" => [
            "Número de reserva:"=> "Bookingreference :",
            "Info de vuelos"    => "Fly information",
            "Recibo de viaje"   => "Rejsekvittering",
            "Precio total"      => "Beløb i alt",
            "Total"             => "I alt",
            "(Cancelled)"       => "(Annulleret)",
        ],
        "fi" => [
            "Número de reserva:"=> "Varausnumero:",
            "Info de vuelos"    => "Lennon tiedot",
            "Recibo de viaje"   => "Matkakuitti",
            "Precio total"      => "Kokonaissumma",
            "Total"             => "Summa",
            //			"(Cancelled)" => "",
        ],
        "it" => [
            "Número de reserva:"=> "Numero della prenotazione:",
            "Info de vuelos"    => "Info volo",
            "Recibo de viaje"   => "Ricevuta di",
            "Precio total"      => "Importo totale",
            "Total"             => "Totale",
            "(Cancelled)"       => "(Cancellato)",
        ],
    ];

    public $lang = "es";
    public $pdf = '';

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Número de reserva:"));

        // TripNumber
        // Passengers
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
        if (!empty($this->pdf)) {
            $totalPos = stripos($this->pdf, $this->t('Precio total'));

            if (!empty($totalPos)) {
                $this->pdf = substr($this->pdf, 0, $totalPos + 300);

                if (preg_match_all("#(?:\n|,)([A-Z ]+)\/([A-Z ]+)\(([\d \-]{5,})\)#", $this->pdf, $m)) {
                    foreach ($m[0] as $key => $pass) {
                        $it['Passengers'][] = ucwords(strtolower(trim($m[2][$key]) . ' ' . trim($m[1][$key])));
                        $it['TicketNumbers'][] = trim($m[3][$key]);
                    }
                }

                if (!empty($it['Passengers'])) {
                    $it['Passengers'] = array_unique(array_filter($it['Passengers']));
                    $it['TicketNumbers'] = array_unique(array_filter($it['TicketNumbers']));
                }

                if (preg_match("#" . $this->t('Total') . "\s*\(([A-Z]{3})\)#", $this->pdf, $m)) {
                    $it['Currency'] = $m[1];
                }

                if (preg_match("#" . $this->t('Precio total') . "[ ]+([\d\.\,]+)[ ]{2,}([\d\.\,]+)[ ]*\n#", $this->pdf, $m)) {
                    $it['Tax'] = $this->amount($m[1]);
                    $it['TotalCharge'] = $this->amount($m[2]);
                }
            }
        }

        $xpath = "//text()[normalize-space(.)='" . $this->t("Info de vuelos") . "']/ancestor::tr[1]/following-sibling::tr/td[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);

            if (!empty($this->pdf) && stripos($this->pdf, $this->t('(Cancelled)')) !== false) {
                // Cancelled
                $it['Cancelled'] = true;
                // Status
                $it['Status'] = trim($this->t('(Cancelled)'), '() ');
            }
        }

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[1]", $root, true, "#\w{2}\d+\s+-\s+(.+)#")));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./descendant::text()[1]", $root, true, "#^\w{2}(\d+)#");

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./descendant::text()[2]", $root, true, "#^\d+:\d+\s+(.+)#");

            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./descendant::text()[2]", $root, true, "#^(\d+:\d+)#"), $date);

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./descendant::text()[3]", $root, true, "#^\d+:\d+\s+(.+)#");

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./descendant::text()[3]", $root, true, "#^(\d+:\d+)#"), $date);

            if ($itsegment['ArrDate'] < $itsegment['DepDate']) {
                $itsegment['ArrDate'] = strtotime("+1 day", $itsegment['ArrDate']);
            }

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./descendant::text()[1]", $root, true, "#^(\w{2})\d+#");

            // Operator
            // Aircraft
            // TraveledMiles
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

        $pdfs = $parser->searchAttachmentByName('.*.pdf');

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!empty($text) && stripos($text, $this->reBody) && stripos($text, $this->t('Recibo de viaje'))) {
                $this->pdf .= $text;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'It4380082_' . ucfirst($this->lang),
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

    private function nextText($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[normalize-space(.)='{$field}'])[{$n}]/following::text()[normalize-space(.)][1]", $root);
    }

    private function nextCol($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//td[not(.//td) and normalize-space(.)='{$field}'])[{$n}]/following-sibling::td[1]", $root);
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
            "#^(\d+)\s+(\d+)\s+(\d{4})$#",
            "#^(\d+)\s+([^\d\s]+)\.\s+(\d{4})$#",
        ];
        $out = [
            "$1.$2.$3",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
            $str = $this->dateStringToEnglish($str);
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }
}
