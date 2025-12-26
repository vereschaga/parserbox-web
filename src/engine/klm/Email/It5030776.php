<?php

namespace AwardWallet\Engine\klm\Email;

class It5030776 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;

    public $mailFiles = "klm/it-5030776.eml, klm/it-5771974.eml";

    public $reBody2 = [
        "en" => "DEPARTURE",
        "nl" => "VERTREK",
        "fr" => "DEPART",
    ];

    public static $dictionary = [
        "en" => [],
        "nl" => [
            "BOOKING REF:"=> "RES. NUMMER:",
            "DATE:"       => "DATUM:",
            "FLIGHT"      => "VLUCHT",
            "DEPARTURE:"  => "VERTREK:",
            "ARRIVAL:"    => "AANKOMST:",
            "OPERATED BY:"=> "VLUCHT UITGEVOERD DOOR:",
            "EQUIPMENT:"  => "VLIEGTUIGTYPE:",
            "DURATION:"   => "DUUR:",
            "SEAT:"       => "ZITPLAATS:",
            "MEAL:"       => "MAALTIJD:",
            "FOR"         => "VOOR",
        ],
        "fr" => [
            "BOOKING REF:"=> "REF. DE DOSSIER:",
            "FLIGHT"      => "VOL",
            "DEPARTURE:"  => "DEPART:",
            "ARRIVAL:"    => "ARRIVEE:",
            "OPERATED BY:"=> "OPERE PAR:",
            "EQUIPMENT:"  => "EQUIPEMENT:",
            "DURATION:"   => "DUREE:",
            "MEAL:"       => "REPAS:",
            "TICKET:"     => "BILLET:",
            "FOR"         => "POUR",
        ],
    ];

    public $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@klm.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'E-SERVICE.NL@KLM.COM') !== false
            || stripos($headers['from'], 'EMAIL.REPLY@KLM.COM') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, 'KLM') === false || !$this->detectEmailFromProvider($parser->getHeader('from'))) {
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
        if (!self::detectEmailByBody($parser) && (stripos($parser->getHeader('from'), '@klm') == false)) {
            return false;
        }
        $this->date = strtotime($parser->getHeader('date'));
        $textBody = empty($parser->getPlainBody()) ? text($parser->getHTMLBody()) : $parser->getPlainBody();

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($textBody, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }
        $it = $this->ParseEmail($textBody);

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$it],
            ],
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    protected function ParseEmail($text)
    {
        //		$nodes = $this->http->XPath->query('(//pre)[1]');
        //		if($nodes->length === 0) return;
        //		$text = $nodes->item(0)->nodeValue;

        $it = [];
        $it['Kind'] = 'T';

        // RecordLocator
        $it['RecordLocator'] = $this->re('/' . $this->t('BOOKING REF:') . '\s+([A-Z\d]{5,7})\s*$/m', $text);

        // ReservationDate
        $it['ReservationDate'] = strtotime($this->normalizeDate(trim($this->re('/' . $this->t('DATE:') . '\s+(\d{2}\s+[^\d\s]+\s+\d{2,4})\s*$/m', $text))));

        // Passengers
        if (preg_match_all('/' . $this->t('TICKET:') . '.+?' . $this->t('FOR') . '\s+(\b.+\b)\s*$/m', $text, $passengers)) {
            $it['Passengers'] = $passengers[1];
        }

        $segments = $this->splitter('/(' . $this->t('FLIGHT') . '\s+\w{2}\s+\d+\s+-)/', $text);

        foreach ($segments as $stext) {
            $itsegment = [];
            $itsegment['AirlineName'] = $this->re("#" . $this->t("FLIGHT") . "\s+(\w{2})\s+\d+#", $stext);
            $itsegment['FlightNumber'] = $this->re("#" . $this->t("FLIGHT") . "\s+\w{2}\s+(\d+)#", $stext);
            $date = strtotime($this->normalizeDate($this->re('/' . $this->t('FLIGHT') . '.+(\d{2}\s+[^\d\s]+\s+\d{2,4})\s*$/m', $stext)));

            if (preg_match('/' . $this->t('DEPARTURE:') . '\s+(.+?)\s+(\d{2}\s+[^\d\s]+\s+\d{2}:\d{2})\s*$/m', $stext, $matches)) {
                if (preg_match('/(.+?),\s+TERMINAL\s+([A-Z\d]{1,3})/', $matches[1], $matches_2)) {
                    $itsegment['DepName'] = $matches_2[1];
                    $itsegment['DepartureTerminal'] = $matches_2[2];
                } else {
                    $itsegment['DepName'] = $matches[1];
                }
                $itsegment['DepDate'] = strtotime($this->normalizeDate($matches[2]), $date);
            }

            if (preg_match('/' . $this->t('ARRIVAL:') . '\s+(.+?)\s+(\d{2}\s+[^\d\s]+\s+\d{2}:\d{2})\s*$/m', $stext, $matches)) {
                if (preg_match('/(.+?),\s+TERMINAL\s+([A-Z\d]{1,3})/', $matches[1], $matches_2)) {
                    $itsegment['ArrName'] = $matches_2[1];
                    $itsegment['ArrivalTerminal'] = $matches_2[2];
                } else {
                    $itsegment['ArrName'] = $matches[1];
                }
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($matches[2]), $date);
            }

            $itsegment['Cabin'] = $this->re("#,\s+(\w+)\s+\(\w\)\s+" . $this->t("DURATION:") . "#", $stext);
            $itsegment['BookingClass'] = $this->re("#,\s+\w+\s+\((\w)\)\s+" . $this->t("DURATION:") . "#", $stext);
            $itsegment['Duration'] = $this->re("#" . $this->t("DURATION:") . "\s+(.+)#", $stext);

            if (preg_match_all("#" . $this->t("SEAT:") . "\s+(\d+\w)#", $stext, $seats)) {
                $itsegment['Seats'] = $seats[1];
            }

            $itsegment['Meal'] = $this->re("#" . $this->t("MEAL:") . "\s+(.+)#", $stext);

            if (preg_match_all('/^[>*\s]*STOP\s+\d+.+$/m', $stext, $matches)) {
                $itsegment['Stops'] = count($matches[0]);
            }

            $itsegment['Operator'] = $this->re("#" . $this->t("OPERATED BY:") . "\s+(.+)#", $stext);
            $itsegment['Aircraft'] = $this->re("#" . $this->t("EQUIPMENT:") . "\s+(.+)#", $stext);

            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
            $it['TripSegments'][] = $itsegment;
        }

        return $it;
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
            "#^[^\d\s]+\s+(\d+\s+[^\d\s]+\s+\d{4})$#",
            "#^(\d+)\s+([^\d\s]+)\s+(\d+:\d+)$#",
        ];
        $out = [
            "$1",
            "$1 $2, $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\s-\./:]#", $str)) {
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

    private function splitter($re, $text)
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
