<?php

namespace AwardWallet\Engine\interjet\Email;

use AwardWallet\Engine\MonthTranslate;

class ItineraryPlain extends \TAccountChecker
{
    public $mailFiles = "";
    public $reFrom = "@interjet.com";
    public $reSubject = [
        "es"=> "Interjet Itinerario",
    ];
    public $reBody = 'Interjet';
    public $reBody2 = [
        "es"=> "Itinerario",
    ];

    public static $dictionary = [
        "es" => [],
    ];

    public $lang = "es";

    public function parsePlain(&$itineraries)
    {
        $text = $this->http->Response['body'];
        // echo $text."\n";die();
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#Código de Confirmación:\s+(\w+)#", $text);

        // TripNumber
        // Passengers
        $ptext = substr($text, strpos($text, "Información de pasajeros:"), strpos($text, "Información de vuelos:"));
        $it['Passengers'] = preg_match_all("#ADT\s+(.*?)\s+\d+#", $ptext, $m) ? $m[1] : [];

        // TicketNumbers
        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->re("#Total\s+[A-Z]{3}:\s+(.+)#", $text));

        // BaseFare
        $it['BaseFare'] = $this->amount($this->re("#Total Tarifa Base:\s+(.+)#", $text));

        // Currency
        $it['Currency'] = $this->re("#Total\s+([A-Z]{3}):\s+#", $text);

        // Tax
        $it['Tax'] = $this->amount($this->re("#Total impuestos:\s+(.+)#", $text));

        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory
        $fltext = substr($text, strpos($text, "Información de vuelos:"), strpos($text, "Total Tarifa Base:"));
        preg_match_all("#(?<Date>\d+/\d+/\d{4})\s+(?<FlightNumber>\d+)\s+(?<DepName>.*?)\s+\((?<DepCode>[A-Z]{3})\)\s+(?<DepTime>\d+:\d+\s+[AP]M)\s+(?<ArrName>.*?)\s+\((?<ArrCode>[A-Z]{3})\)\s+(?<ArrTime>\d+:\d+\s+[AP]M)#s", $fltext, $segments, PREG_SET_ORDER);

        foreach ($segments as $s) {
            $date = strtotime($this->normalizeDate($s['Date']));

            $itsegment = [];
            $keys = ['FlightNumber', 'DepName', 'DepCode', 'ArrName', 'ArrCode'];

            foreach ($keys as $key) {
                $itsegment[$key] = $s[$key];
            }

            $itsegment['DepDate'] = strtotime($this->normalizeTime($s['DepTime']), $date);
            $itsegment['ArrDate'] = strtotime($this->normalizeTime($s['ArrTime']), $date);

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
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

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

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                return false;
            }
        }

        $this->http->setBody($parser->getPlainBody());

        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePlain($itineraries);

        $result = [
            'emailType'  => end(explode('\\', __CLASS__)) . ucfirst($this->lang),
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
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
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
            "#^(\d+)/(\d+)/(\d{4})$#", //29/12/2014
        ];
        $out = [
            "$1.$2.$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function normalizeTime($str)
    {
        if ($this->lang == 'es') {
            $str = preg_replace("#^(\d+:\d+)\s+[AP]M$#", "$1", $str);
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

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
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
