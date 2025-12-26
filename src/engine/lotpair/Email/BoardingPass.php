<?php

namespace AwardWallet\Engine\lotpair\Email;

// parsers with similar PDF-formats: airtransat/BoardingPass, asiana/BoardingPassPdf, aviancataca/BoardingPass, aviancataca/TicketDetails, czech/BoardingPass, sata/BoardingPass, tamair/BoardingPassPDF(object), tapportugal/AirTicket, luxair/YourBoardingPassNonPdf, saudisrabianairlin/BoardingPass

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "lotpair/it-6486093.eml, lotpair/it-6508386.eml, lotpair/it-6571829.eml";

    public $reFrom = "lotpair.com";
    public $reBody = [
        'en' => ['Boarding Pass', 'From'],
        'pl' => ['Karta pokładowa', 'WYLOT'],
    ];
    public $reSubject = [
        'LOT Boarding Pass Confirmation',
        'Karta Pokladowa LOT',
    ];
    public $lang = '';
    public $pdf;
    public $pdfNamePattern = "LOTboardingPass.*pdf";
    public static $dict = [
        'en' => [
            'BOOKING REF' => 'BOOKING REF(?:ERENCE)?',
            //			'Boarding Pass' => '',
            //			'TO' => '',
            //			'FLIGHT' => '',
            //			'GATE' => '',
            //			'ZONE' => '',
            'CLASS' => '(?:\/\s+CLASS|CLASS OF TRAVEL)',
        ],
        'pl' => [
            'BOOKING REF'   => 'Numer rezerwacji',
            'Boarding Pass' => 'Karta pokładowa',
            'TO'            => 'DO',
            'FLIGHT'        => 'Numer rejsu',
            'GATE'          => 'Wyjście',
            'ZONE'          => 'Zone',
            'CLASS'         => 'Klasa[ ]+podróży',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $typeParsing = "";
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;

            foreach ($pdfs as $pdf) {
                if (($text = text(\PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE))) !== null) {
                    $its[] = $this->parseEmailPDF($text);
                    $typeParsing = "PDF";
                } else {
                    return null;
                }
            }
        } else {
            $body = text($parser->getPlainBody());

            if (!empty($body)) {
                $its = $this->parseEmailPlain($body);
                $typeParsing = "Plain";
            } else {
                return null;
            }
        }

        $class = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end($class) . $typeParsing . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            if (strpos($text, 'LOT') != false) {
                return $this->AssignLang($text);
            }
        } else {
            $body = $parser->getPlainBody();

            if (!empty($body)) {
                return strpos($body, 'LOT') != false && $this->AssignLang($body);
            }
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
        $cnt = count(self::$dict);

        return $cnt * 2;
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

    private function parseEmailPDF($text)
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->re("#" . $this->t("BOOKING REF") . "\s*\n(?:.*\n){0,5}?\s*([A-Z\d]{5,})\s*\n#", $text);
        $it['TicketNumbers'][] = $this->re("#ETKT\s+([A-Z\d\-]{8,})\s*\n#", $text);
        $it['Passengers'][] = $this->re("#" . $this->t("Boarding Pass") . "\s+(.+)#", $text);

        $seg = [];

        if (preg_match("#\s+" . $this->t("TO") . "\n.+?\n.+?\n\s*(\d+:\d+)\s*([A-Z]{3})\s*([A-Z]{3})\s*(\d+:\d+)\s*(\d+\s+\w+\s+\d{4})\s*(\d+\s+\w+\s+\d{4})#", $text, $m)) {
            $seg['DepCode'] = $m[2];
            $seg['ArrCode'] = $m[3];
            $date = strtotime($m[5]);
            $seg['DepDate'] = strtotime($m[1], $date);
            $seg['ArrDate'] = strtotime($m[4], $date);
        } elseif (preg_match("#\s+" . $this->t("TO") . "\n.+?\n.+?\n\s*(\d+:\d+)\s*(\d+:\d+)\s*(\d+\s+\w+\s+\d{4})\s*([A-Z]{3})\s*([A-Z]{3})\s*(\d+\s+\w+\s+\d{4})#", $text, $m)) {
            $seg['DepCode'] = $m[4];
            $seg['ArrCode'] = $m[5];
            $date = strtotime($m[3]);
            $seg['DepDate'] = strtotime($m[1], $date);
            $seg['ArrDate'] = strtotime($m[2], $date);
        }

        if (preg_match("#\n(.+)\n(.+)\n.*?" . $this->t("FLIGHT") . "#", $text, $m)) {
            $seg['DepName'] = trim($m[1]);
            $seg['ArrName'] = trim($m[2]);
        }

        if (preg_match("#" . $this->t("GATE") . "\s*(?:" . $this->t("ZONE") . "\s*)?.*?\n\s*([A-Z\d]{2})\s*(\d+)\s+(\d{1,3}[A-Za-z]\s*\n)?(?:.*\n)?\s*([A-Z]{1,2})\s*\n#", $text, $m)) {
            $seg['AirlineName'] = $m[1];
            $seg['FlightNumber'] = $m[2];

            if (isset($m[3]) && !empty($m[3])) {
                $seg['Seats'][] = trim($m[3]);
            }

            if (isset($m[4]) && !empty($m[4])) {
                $seg['BookingClass'] = $m[4];
            }
        }
        $seg['Cabin'] = $this->re("#" . $this->t("CLASS") . "\s+(?:.*\n){0,5}\s*([A-Z][A-Z ]{2,})#", $text);

        $it['TripSegments'][] = $seg;

        return $it;
    }

    private function parseEmailPlain($text)
    {
        $text = strstr($text, 'Your Travel Documents'); //exclude FW-information, like "From : xxxx@xxxx.xx"  and "To : xxxx@xxxx.xx", only body

        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->re("#[>\n\s]*Booking Reference:[>\n\s]*([A-Z\d]{5,}+)#", $text);
        $it['Passengers'][] = $this->re("#[>\n\s]*Passenger:[>\n\s]*(.+)#", $text);

        $seg = [];
        $node = $this->re("#[>\n\s]*Flight:[>\n\s]*(.+)#", $text);

        if (preg_match("#([A-Z\d]{2})\s*(\d+)\s*\-?\s*([A-Z]{1,2})?#", $node, $m)) {
            $seg['AirlineName'] = $m[1];
            $seg['FlightNumber'] = $m[2];

            if (isset($m[3]) && !empty($m[3])) {
                $seg['BookingClass'] = $m[3];
            }
        }
        $seg['DepName'] = $this->re("#[>\n\s]*From:[>\n\s]*(.+)#", $text);
        $seg['DepDate'] = strtotime($this->normalizeDate($this->re("#[>\n\s]*From:[>\n\s]*.+[>\n\s]*(.+)#", $text)));
        $seg['ArrName'] = $this->re("#[>\n\s]*To:[>\n\s]*(.+)#", $text);
        $seg['ArrDate'] = strtotime($this->normalizeDate($this->re("#[>\n\s]*To:[>\n\s]*.+[>\n\s]*(.+)#", $text)));
        $seg['DepCode'] = TRIP_CODE_UNKNOWN;
        $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

        $it['TripSegments'][] = $seg;

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*(\d+)\s+(\w+)\s+(\d{4})\s*\-\s*(\d+:\d+\s*(?:[ap]m)?)\s*$#i',
        ];
        $out = [
            '$1 $2 $3 $4',
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

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
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
