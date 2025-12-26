<?php

namespace AwardWallet\Engine\primera\Email;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "primera/it-7627249.eml";

    public $reFrom = "@primeraair.com";
    public $reProvider = "primeraair";
    public $reBodyPDF = [
        'en' => ['Booking ref'],
    ];
    public $reSubject = [
        'Your Primera Air boarding pass',
    ];
    public $lang = '';
    public $pdf;
    public $pdfNamePattern = "boardingpass.*\.pdf";
    public static $dict = [
        'en' => [],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (!isset($pdfs[0])) {
            return false;
        }

        foreach ($pdfs as $pdf) {
            $body = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($body === null) {
                continue;
            }
            $this->pdf = clone $this->http;
            $this->pdf->SetEmailBody($body);
            $its[] = $this->parseEmail();
        }
        $name = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end($name) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            foreach ($this->reBodyPDF as $reBody) {
                if (stripos($text, $reBody[0]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers["subject"], $reSubject) !== false) {
                return true;
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
            return [];
        }

        $r = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            if (empty(trim($r[0]))) {
                array_shift($r);
            }

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
        $segments = $this->splitText("#(Booking ref)#", $this->pdf->Response['body']);

        foreach ($segments as $key => $segment) {
            $seg = [];

            if (preg_match("#Booking ref\s+([A-Z\d]{5,6})\s+([A-Z\-\s]+)Date#", $segment, $m)) {
                $it['RecordLocator'] = $m[1];
                $it['Passengers'][] = trim($m[2]);
            }

            if (preg_match("#Date\s+Gate closes\s+Departs at\s+(\d{2})([\w]{3})(\d{2})\s+[\d:]+\s+([\d:]+)\s*\n#", $segment, $m)) {
                $seg['DepDate'] = strtotime($m[1] . ' ' . $m[2] . ' 20' . $m[3] . ' ' . $m[4]);
                $seg['ArrDate'] = MISSING_DATE;
            }

            if (preg_match("#\n\s*([A-Z][\w\-,. ]+)\s+([A-Z\d]{2})\s+(\d{1,5})\s+([A-Z][\w\-,. ]+)\s+([A-Z]{3})\s+([A-Z]{3})#u", $segment, $m)) {
                $seg['AirlineName'] = $m[2];
                $seg['FlightNumber'] = $m[3];
                $seg['DepName'] = trim($m[1]);
                $seg['DepCode'] = $m[5];
                $seg['ArrName'] = trim($m[4]);
                $seg['ArrCode'] = $m[6];
            }

            if (preg_match("#\s+Seat.+\s+(\d{1,3}[A-Z])\s+#", $segment, $m)) {
                $seg['Seats'][] = $m[1];
            }

            if (preg_match("#\n(.+Meal.*\n.+\n.*)#", $segment, $m)) {
                $meal = explode("\n", $m[1]);
                $mealPos = strpos($meal[0], 'Meal');
                $seg['Meal'] = trim(substr($meal[1], $mealPos));

                if (!empty(trim(substr($meal[2], $mealPos)))) {
                    $seg['Meal'] .= " " . trim(substr($meal[2], $mealPos));
                }
            }

            $finded = false;

            foreach ($it['TripSegments'] as $key => $value) {
                if (isset($seg['AirlineName']) && $seg['AirlineName'] == $value['AirlineName'] && isset($seg['FlightNumber']) && $seg['FlightNumber'] == $value['FlightNumber']) {
                    $it['TripSegments'][$key]['Seats'] = array_merge($value['Seats'], $seg['Seats']);
                    $finded = true;
                }
            }

            if ($finded == false) {
                $it['TripSegments'][] = $seg;
            }
        }

        return $it;
    }
}
