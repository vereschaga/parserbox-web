<?php

namespace AwardWallet\Engine\indigo\Email;

class YourIndiGoBP extends \TAccountChecker
{
    public $mailFiles = "indigo/it-7541849.eml, indigo/it-7592261.eml, indigo/it-7600004.eml";

    public $reFrom = "marketing@goindigo.in";
    public $reBody = [
        'en' => ['You have successfully checked in', 'IndiGo'],
    ];
    public $reBodyPDF = [
        'en' => ['goIndiGo.in', 'Boarding Pass'],
    ];
    public $reSubject = [
        'Your IndiGo booking reference no',
    ];
    public $lang = '';
    public $pdf;
    public $pdfNamePattern = "BoardingPass.*pdf";
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $type = '';
        $its = [];
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = text(\PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE))) !== null) {
                    $this->AssignLang($text, true);
                    $arr = $this->splitter("#(\(Mobile)#", $text);

                    foreach ($arr as $value) {
                        $its[] = $this->parseEmailPDF($value);
                    }
                    $type = 'PDF';
                }
            }
        } else {
            $body = $this->http->Response['body'];
            $this->AssignLang($body);
            $its = $this->parseEmailHTML();
            $type = 'HTML';
        }

        $its = $this->mergeItineraries($its);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if ($this->AssignLang($body)) {
            return true;
        }

        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            return $this->AssignLang($text);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
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

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    protected function mergeItineraries($its)
    {
        $its2 = $its;

        foreach ($its2 as $i => $it) {
            if ($i && ($j = $this->findRL($i, $it['RecordLocator'], $its)) != -1) {
                foreach ($its[$j]['TripSegments'] as $flJ => $tsJ) {
                    foreach ($its[$i]['TripSegments'] as $flI => $tsI) {
                        if (isset($tsI['FlightNumber']) && isset($tsJ['FlightNumber']) && ((int) $tsJ['FlightNumber'] === (int) $tsI['FlightNumber'])) {
                            $new = "";

                            if (isset($tsJ['Seats'])) {
                                $new .= "," . $tsJ['Seats'];
                            }

                            if (isset($tsI['Seats'])) {
                                $new .= "," . $tsI['Seats'];
                            }
                            $new = implode(",", array_filter(array_unique(array_map("trim", explode(",", $new)))));
                            $its[$j]['TripSegments'][$flJ]['Seats'] = $new;
                            $its[$i]['TripSegments'][$flI]['Seats'] = $new;
                        }
                    }
                }

                $its[$j]['TripSegments'] = array_merge($its[$j]['TripSegments'], $its[$i]['TripSegments']);
                $its[$j]['TripSegments'] = array_map('unserialize', array_unique(array_map('serialize', $its[$j]['TripSegments'])));
                $its[$j]['Passengers'] = array_merge($its[$j]['Passengers'], $its[$i]['Passengers']);
                $its[$j]['Passengers'] = array_map("unserialize", array_unique(array_map("serialize", $its[$j]['Passengers'])));
                unset($its[$i]);
            }
        }

        return $its;
    }

    protected function findRL($g_i, $rl, $its)
    {
        foreach ($its as $i => $it) {
            if ($g_i != $i && $it['RecordLocator'] === $rl) {
                return $i;
            }
        }

        return -1;
    }

    private function parseEmailPDF($textPDF)
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->re("#PNR[\s:]+([A-Z\d]{5,})#", $textPDF);
        $it['Passengers'][] = $this->re("#Name[\s:]+(.+)#", $textPDF);

        $seg['DepCode'] = TRIP_CODE_UNKNOWN;
        $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
        $seg['DepName'] = $this->re("#From[\s:]+(.+)#", $textPDF);
        $seg['ArrName'] = $this->re("#To[\s:]+(.+)#", $textPDF);
        $seg['AirlineName'] = $this->re("#Flight No\.?[\s:]+([A-Z\d]{2})\s*\d+#", $textPDF);
        $seg['FlightNumber'] = $this->re("#Flight No\.?[\s:]+[A-Z\d]{2}\s*(\d+)#", $textPDF);
        $seg['DepDate'] = strtotime($this->re("#Departure Time[\s:]+(.+)#", $textPDF), strtotime($this->normalizeDate($this->re("#Date[\s:]+(.+)#", $textPDF))));
        $seg['ArrDate'] = MISSING_DATE;
        $seg['BookingClass'] = $this->re("#Class[\s:]+([A-Z]{1,2})\n#", $textPDF);
        $seg['Seats'] = $this->re("#Seat No\.?[\s:]+(\d+[A-Z])\n#", $textPDF);
        $it['TripSegments'][] = $seg;

        return $it;
    }

    private function parseEmailHTML()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Booking Reference No')]/following::text()[normalize-space(.)!=''][1]");
        $it['Passengers'][] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Dear')]", null, true, "#Dear\s+(.+?)(?:\.|$)#");
        $str = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'You have successfully checked in')]");

        if (preg_match("#between\s+(.+?)\s+and\s+(.+?)\s+on\s+([A-Z\d]{2})\s*(\d+)\s+on\s+(.+?)\s+departing at\s+(\d+:\d+(?:\s*[ap]m)?)(?:\.|$)#i", $str, $m)) {
            $seg['DepName'] = $m[1];
            $seg['ArrName'] = $m[2];
            $seg['AirlineName'] = $m[3];
            $seg['FlightNumber'] = $m[4];
            $seg['DepDate'] = strtotime($m[6], strtotime($this->normalizeDate($m[5])));
            $seg['ArrDate'] = MISSING_DATE;
        }
        $seg['DepCode'] = TRIP_CODE_UNKNOWN;
        $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

        $it['TripSegments'][] = $seg;

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*(\d+\{2})\-(\w+)\-(\d{2})\s*$#',
        ];
        $out = [
            '$1 $2 20$3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body, $isPDF = false)
    {
        if ($isPDF) {
            $search = $this->reBodyPDF;
        } else {
            $search = $this->reBody;
        }

        foreach ($search as $lang => $reBody) {
            if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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
