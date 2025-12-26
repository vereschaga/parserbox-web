<?php

namespace AwardWallet\Engine\panorama\Email;

class ETicketConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "panorama/it-12070491.eml, panorama/it-5176409.eml, panorama/it-5184213.eml, panorama/it-5184525.eml, panorama/it-5204429.eml, panorama/it-5239099.eml, panorama/it-5574264.eml, panorama/it-5624652.eml, panorama/it-6211636.eml, panorama/it-6247816.eml, panorama/it-6247826.eml, panorama/it-6288666.eml, panorama/it-6293813.eml, panorama/it-6323526.eml, panorama/it-6401022.eml, panorama/it-6401091.eml, panorama/it-6464671.eml";

    public $reFrom = "link@bmp.viaamadeus.com";
    public $reSubject = [
        "en"=> "This is your e-Ticket confirmation",
    ];
    public $reBody = 'Ukraine International Airlines';
    public $reBody2 = [
        "fr"=> "DUREE",
        "it"=> "PARTENZA",
        "uk"=> "ВИЛІТ",
        "ru"=> "ВЫЛЕТ",
        "de"=> "ABFLUG",
        "es"=> "SALIDA",
        "en"=> "DEPARTURE",
    ];
    /** @var \HttpBrowser */
    public $pdf;
    public static $dictionary = [
        "en" => [],
        "fr" => [
            "PASSENGER"      => "PASSAGER",
            "FLIGHT"         => "VOL",
            "DEPARTURE"      => "DEPART",
            "ARRIVAL"        => "ARRIVEE",
            "DURATION"       => "DUREE",
            "FARE BASIS"     => "BASE TARIFAIRE",
            "BAGGAGE"        => "BAGAGE",
            "EQUIPMENT"      => "EQUIPEMENT",
            "CLASS"          => "CLASSE",
            "MEAL"           => "MENU",
            "CONNECTION TIME"=> "(?:TEMPS DE|CONNEXION)",
            "MILEAGE"        => "DISTANCE",
        ],
        "it" => [
            "PASSENGER"      => "PASSEGGERO",
            "FLIGHT"         => "VOLO",
            "DEPARTURE"      => "PARTENZA",
            "ARRIVAL"        => "ARRIVO",
            "DURATION"       => "DURATA",
            "FARE BASIS"     => "BASE TARIFFARIA",
            "BAGGAGE"        => "BAGAGLIO",
            "EQUIPMENT"      => "ATTREZZATURA",
            "CLASS"          => "CLASSE",
            "SEAT"           => "POSTO",
            "MEAL"           => "PASTO",
            "CONNECTION TIME"=> "(?:TEMPO DI|COINCIDENZA)",
            "MILEAGE"        => "MIGLIAGGIO",
        ],
        "uk" => [
            "PASSENGER"      => "ПАСАЖИР",
            "FLIGHT"         => "РЕЙС",
            "DEPARTURE"      => "ВИЛІТ",
            "ARRIVAL"        => "ПРИБУТТЯ",
            "DURATION"       => "ТРИВАЛІСТЬ",
            "FARE BASIS"     => "ВИД ТАРИФУ",
            "BAGGAGE"        => "БАГАЖ",
            "EQUIPMENT"      => "ТИП ПС",
            "CLASS"          => "КЛАС",
            "SEAT"           => "МІСЦЕ",
            "MEAL"           => "ХАРЧУВАННЯ",
            "CONNECTION TIME"=> "NOTTRANBSLATED",
            "MILEAGE"        => "ВІДСТАНЬ",
        ],
        "ru" => [
            "PASSENGER"      => "ПАССАЖИР",
            "FLIGHT"         => "РЕЙС",
            "DEPARTURE"      => "ВЫЛЕТ",
            "ARRIVAL"        => "ПРИБЫТИЕ",
            "DURATION"       => "ПРОДОЛЖИТЕЛЬНОСТЬ",
            "FARE BASIS"     => "ВИД ТАРИФА",
            "BAGGAGE"        => "БАГАЖ",
            "EQUIPMENT"      => "ТИП ВС",
            "CLASS"          => "КЛАСС",
            "SEAT"           => "МЕСТО",
            "MEAL"           => "ПИТАНИЕ",
            "CONNECTION TIME"=> "NOTTRANBSLATED",
            "MILEAGE"        => "РАССТОЯНИЕ",
        ],
        "de" => [
            "PASSENGER"      => "PASSAGIER",
            "FLIGHT"         => "FLUG",
            "DEPARTURE"      => "ABFLUG",
            "ARRIVAL"        => "ANKUNFT",
            "DURATION"       => "DAUER",
            "FARE BASIS"     => "TARIFART",
            "BAGGAGE"        => "GEPÄCK",
            "EQUIPMENT"      => "AUSRÜSTUNG",
            "CLASS"          => "KLASSE",
            "SEAT"           => "NOTTRANBSLATED",
            "MEAL"           => "MAHLZEIT",
            "CONNECTION TIME"=> "NOTTRANBSLATED",
            "MILEAGE"        => "MEILEN",
        ],
        "es" => [
            "PASSENGER"      => "PASAJERO",
            "FLIGHT"         => "VUELO",
            "DEPARTURE"      => "SALIDA",
            "ARRIVAL"        => "LLEGADA",
            "DURACIÓN"       => "DAUER",
            "FARE BASIS"     => "BASE DE TARIFA",
            "BAGGAGE"        => "EQUIPAJE",
            "EQUIPMENT"      => "EQUIPO",
            "CLASS"          => "CLASE",
            "SEAT"           => "ASIENTO",
            "MEAL"           => "COMIDA",
            "CONNECTION TIME"=> "(?:TIEMPO DE|CONEXIÓN)",
            "MILEAGE"        => "MILLAS",
        ],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->pdf->Response["body"];
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#\n\w{2}/([A-Z\d]{5,6})\n#", $text);

        // Passengers
        if (!$passenger = $this->re("#" . $this->t("PASSENGER") . "\n(.*?)\n\d+#", $text)) {
            if (!$passenger = $this->re("#" . $this->t("PASSENGER") . "\n\d+-\d+-\d+\n(.*?)\n#", $text)) {
                $passenger = $this->re("#" . $this->t("PASSENGER ITINERARY RECEIPT") . "\n\d+-\d+-\d+\n([A-Z ]+)\n#", $text);
            }
        }

        $it['Passengers'] = array_filter([$passenger]);

        $segments = $this->split("#(\n[A-Z]{3}\s+-\s+[A-Z]{3}\n)#", $text);

        foreach ($segments as $k=> $segment) {
            if (strpos($segment, "FLIGHT\n") === false) {
                unset($segments[$k]);
            }
        }

        if (substr_count($text, "\nDEPARTURE\n") != count($segments)) {
            $this->http->Log("incorrect segments count");

            return false;
        }

        foreach ($segments as $skey => $stext) {
            $itsegment = [];

            // header
            if (!preg_match("#(?<DepCode>[A-Z]{3})\s+-\s+(?<ArrCode>[A-Z]{3})\n" .
            "(?:(?:" . $this->t("FLIGHT") . ")?/?FLIGHT\n|" .
            "(?<AirlineName>\w{2})\s+(?<FlightNumber>\d+)\n|" .
            "(?<Date>.*?\d{4})\n){3}#", $stext, $m)) {
                $this->http->Log("header not matched key:" . $skey);

                return null;
            }

            $keys = [
                'DepCode',
                'ArrCode',
                'AirlineName',
                'FlightNumber',
            ];

            foreach ($keys as $key) {
                if (!isset($m[$key])) {
                    return null;
                }
                $itsegment[$key] = $m[$key];
            }

            if (!isset($m['Date'])) {
                return null;
            }
            $date = strtotime($this->normalizeDate($m["Date"]));
            //middle
            if (!preg_match("#\n(?:(?<DepName>[^\n]+)\n" .
            "\([A-Z]{3}\)(?:,\s+(?<DepartureTerminal>terminal \w+))?\n|" .
            "(?<DepTime>\d+:\d+)\n|" .
            "DEPARTURE\n|" .
            $this->t("DEPARTURE") . "\n){3,4}" .
            "(?:[^\n]+\n)?" .
            "(?:(?<ArrName>[^\n]+)\n" .
            "\([A-Z]{3}\)(?:,\s+(?<ArrivalTerminal>terminal \w+))?\n|" .
            "\+[\d\.]+d\n|" .
            "(?<ArrTime>\d+:\d+)\n|" .
            "ARRIVAL\n|" .
            $this->t("ARRIVAL") . "\n){3,5}" .
            "#", $stext, $m)) {
                $this->http->Log("middle not matched key:" . $skey);

                return null;
            }

            $keys = [
                'DepName',
                'ArrName',
                'DepartureTerminal',
                'ArrivalTerminal',
            ];

            foreach ($keys as $key) {
                if (isset($m[$key])) {
                    $itsegment[$key] = $m[$key];
                }
            }

            if (!isset($m['DepTime']) || !isset($m['ArrTime'])) {
                $this->http->Log("DepTime or ArrTime not matched key:" . $skey);

                return null;
            }
            $itsegment['DepDate'] = strtotime($m['DepTime'], $date);
            $itsegment['ArrDate'] = strtotime($m['ArrTime'], $date);

            $itsegment['Duration'] = $this->re("#\n(\d+-\d+)\n#", $stext);

            // Aircraft, Class
            preg_match("#" .
            "BAGGAGE\n" .
            "(?:" . $this->t("EQUIPMENT") . "\n|" .
            $this->t("CLASS") . "\n|" .
            "EQUIPMENT\n|" .
            "CLASS\n|" .
            "(?<Aircraft>[A-Z\d]{3})\n|" .
            "\(.*?\)\n|" .
            "(?<BookingClass>[A-Z])\n)+" .
            "#", $stext, $m);

            $keys = [
                'Aircraft',
                'BookingClass',
            ];

            foreach ($keys as $key) {
                if (isset($m[$key])) {
                    $itsegment[$key] = $m[$key];
                }
            }

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
        $pdfs = $parser->searchAttachmentByName('(tk_[\d-]+\.pdf|flight.pdf)');

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

        $a = explode('\\', __CLASS__);
        $result = [
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
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
        // echo "->".$str."\n";
        //$year = date("Y", $this->date);
        $in = [
            "#^[^\d\s]+\.\s+(\d+)\s+([^\d\s]+)\.\s+(\d{4})/[^\d\s]+\s+\d+\s+[^\d\s]+\s+\d{4}$#", //ПТ. 22 ЯНВ. 2016/FRI 22 JAN 2016
            "#^[^\d\s]+\.\s+(\d+)\s+([^\d\s]+)\.\s+(\d{4})$#", //GIO. 09 GIU. 2016
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s]+)\s+(\d{4})$#", //GIO 09 GIU 2016
            "#^[^\d\s]+,\s+(\d+)-([^\d\s]+)-(\d{4})$#", //ПН, 14-НОЯ-2016
            "#^[^\d\s]+\.\s+(\d+)\s+([^\d\s]+)\.\s+(\d{4})$#", //ПН. 14 НОЯБ. 2016
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s]+)\s+(\d{4})/[^\d\s]+\s+\d+\s+[^\d\s]+|s+\d{4}$#", //FR 27 FEB 2015/FRI 27 FEB 2015
            "#^[^\d\s]+,\s+(\d+)-([^\d\s]+)-(\d{4})/[^\d\s]+\s+\d+\s+[^\d\s]+\s+\d{4}$#", //СБ, 18-КВІ-2015/SAT 18 APR 2015
        ];
        $out = [
            "$1 $2 $3",
            "$1 $2 $3",
            "$1 $2 $3",
            "$1 $2 $3",
            "$1 $2 $3",
            "$1 $2 $3",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // echo "<-".$str."\n";
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

    private function sortedPdf($parser)
    {
        $pdfs = $parser->searchAttachmentByName('(tk_[\d-]+\.pdf|flight.pdf)');

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
            $nodes = $this->pdf->XPath->query(".//p[contains(translate(normalize-space(.), '1234567890qwertyuiopasdfghjkl;zxcvbnm,./QWERTYUIOPASDFGHJKL:ZXCVBNMйцукенгшщзхъфывапролджэячсмитьбюЙЦУКЕНГШЩЗХЪФЫВАПРОЛДЖЭЯЧСМИТЬБЮ', '___________________________________________________________________________________________________________________________________'), '_')]", $page);

            $cols = [];
            $grid = [];

            foreach ($nodes as $node) {
                $text = implode("\n", array_filter($this->pdf->FindNodes("./descendant::text()[normalize-space(.)!='']", $node)));
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

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
