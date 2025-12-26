<?php

namespace AwardWallet\Engine\indigo\Email;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "indigo/it-2105850.eml, indigo/it-30756821.eml, indigo/it-31093397.eml, indigo/it-7646976.eml, indigo/it-7656512.eml";

    public static $dict = [
        'en' => [],
    ];

    private $reFrom = "reservations@goindigo.in";

    private $reProvider = "goindigo";

    private $reBodyPDF = [
        'en' => ['Boarding Pass', 'goindigo.in'],
    ];

    private $reSubject = [
        'Boarding Pass for PNR',
    ];

    private $lang = '';

    private $pdf;

    private $pdfNamePattern = "BoardingPass.*\.pdf";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (!isset($pdfs[0])) {
            return false;
        }

        foreach ($pdfs as $pdf) {
            $html = text(\PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX));

            if ($html === null) {
                continue;
            }
            $this->pdf = clone $this->http;
            $this->pdf->SetEmailBody($html);
            $its[] = $this->parseEmail();
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $text = str_replace(chr(194) . chr(160), ' ', $text);

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
        return stripos($from, $this->reProvider) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function splitText($pattern, $text)
    {
        if (empty($text)) {
            return $text;
        }

        $r = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
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

    private function parseEmail()
    {
        $it = ['Kind' => 'T'];

        $it['TripSegments'] = [];
        $it['Passengers'] = [];
        $segments = $this->splitText("#(Boarding Pass[ ]*\(|Boarding Confirmation)#i", $this->pdf->Response['body']);

        foreach ($segments as $key => $segment) {
            $seg = [];

            if (preg_match("#PNR\s*:\s+([A-Z\d]{5,6})\s*Flt#", $segment, $m)) {
                $it['RecordLocator'] = $m[1];
            }

            if (preg_match("#Name\s+(.+)\s+From#", $segment, $m)) {
                $it['Passengers'][] = $m[1];
            }

            if (preg_match("#From\s+(.+)\s+To\s+(.+)\s+Flight#", $segment, $m)) {
                $seg['DepName'] = $this->re("#(.+?)\s*(?:\(\s*T[\w\s]+\)|$)#", $m[1]);

                if (!empty($term = $this->re("#.+?\(\s*T([\w\s]+)\)#", $m[1]))) {
                    $seg['DepartureTerminal'] = $term;
                }
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrName'] = $this->re("#(.+?)\s*(\(\s*T[\w\s]+\)|$)#", $m[2]);

                if (!empty($term = $this->re("#.+?\(\s*T([\w\s]+)\)#", $m[2]))) {
                    $seg['ArrivalTerminal'] = $term;
                }

                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            if (preg_match("#Flight No\.\s+([A-Z\d]{2})\s*(\d{1,5})\s+Date#", $segment, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            if (preg_match("#Date\s+(\d+)\s*([\D\S]{3})\s*(\d{2})\s+Boarding Time\s+.*\s+Departure Time\s+(\d+:\d+)#", $segment, $m)) {
                $seg['DepDate'] = strtotime($m[1] . ' ' . $m[2] . ' 20' . $m[3] . ' ' . $m[4]);
                $seg['ArrDate'] = MISSING_DATE;
            }

            if (preg_match("#Class\s+([A-Z]{1,2})\s+Gate\s+#", $segment, $m)) {
                $seg['BookingClass'] = $m[1];
            }

            if (preg_match("#Seat (?:No\.|\\#)\s*(\d+[A-Z])\s+#", $segment, $m)) {
                $seg['Seats'][] = $m[1];
            }
            $finded = false;

            foreach ($it['TripSegments'] as $i => $value) {
                if (isset($seg['AirlineName']) && ($seg['AirlineName'] == $value['AirlineName']) && isset($seg['FlightNumber']) && ($seg['FlightNumber'] == $value['FlightNumber'])) {
                    $it['TripSegments'][$i]['Seats'] = array_unique(array_merge($value['Seats'], $seg['Seats']));
                    $finded = true;
                }
            }

            if ($finded == false && !empty($seg['FlightNumber']) && !empty($seg['AirlineName'])) {
                $it['TripSegments'][] = $seg;
            }
        }
        $it['Passengers'] = array_unique($it['Passengers']);

        return $it;
    }

    private function AssignLang($body)
    {
        foreach ($this->reBodyPDF as $lang => $reBody) {
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
