<?php

namespace AwardWallet\Engine\hawaiian\Email;

class BoardingPassPDF extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "hawaiian/it-4636627.eml";

    public $reBody = [
        'en' => ['our feedback helps us improve our service', 'Please take our simple survey at'],
    ];
    public $reSubject = [
        'en' => ['Hawaiian Air Tickets'],
    ];
    public $lang = '';
    public $pdf;
    public static $dict = [
        'en' => [
        ],
    ];

    /*
        protected function getDate($nodeForDate){
            $month = $this->monthNames['en'];
            $monthLang = $this->monthNames[$this->lang];

            preg_match("#(?<dayOfWeek>.+)\s+(?<day>[\d]+)\s+(?<month>.+)\s+(?<year>\d{4})#", $nodeForDate, $chek);

            for($i = 0; $i < 12; $i++){
                if($monthLang[$i] == strtolower(trim($chek['month']))){
                    $chek['month'] = preg_replace("#[\w]+#i", $month[$i], $chek['month']);
                    $res = strtotime($chek['month'] . ' ' . $chek['day'] . ' ' . $chek['year']);
                }
            }
            return $res;
        }
    */

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName(".*pdf");

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $html = '';

            foreach ($pdfs as $pdf) {
                if (($html .= \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                } else {
                    return null;
                }
            }
            $this->pdf->SetBody($html);
        } else {
            return null;
        }

        $body = $this->pdf->Response['body'];
        $this->AssignLang($body);

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "BoardingPassPDF",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($text, $reBody[0]) !== false && stripos($text, $reBody[1]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && stripos($headers['subject'], 'Hawaiian Air Tickets') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "hawaiianairlines.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->pdf->FindSingleNode("(//p[contains(.,'" . $this->t('Conf') . ":')]/following::b[1])[1]");
        $it['Passengers'] = array_unique($this->pdf->FindNodes("//p[contains(.,'" . $this->t('Name') . ":')]/following::b[1]"));
        $it['AccountNumbers'] = implode(',', array_unique($this->pdf->FindNodes("//p[contains(.,'" . $this->t('Lylty#') . ":')]/following::b[1]")));
        $segs = [];
        $flight = $this->pdf->FindSingleNode("(//p[contains(.,'" . $this->t('Flight') . ":')]/following::b[1])[1]");

        if (isset($flight) && preg_match("#([A-Z\d]{2})\s*(\d+)#", $flight, $m)) {
            $segs['FlightNumber'] = $m[2];
            $segs['AirlineName'] = $m[1];
        }
        $node = $this->pdf->FindSingleNode("(//p[contains(.,'" . $this->t('Departs') . ":')]/following::b[contains(.,'/') and position()=2])[1]");

        if (isset($node) && preg_match("#(.+)\/([A-Z]{3})#", $node, $m)) {
            $segs['DepName'] = $m[1];
            $segs['DepCode'] = $m[2];
        }
        $node = $this->pdf->FindSingleNode("(//p[contains(.,'" . $this->t('Arrives') . ":')]/following::b[contains(.,'/') and position()=2])[1]");

        if (isset($node) && preg_match("#(.+)\/([A-Z]{3})#", $node, $m)) {
            $segs['ArrName'] = $m[1];
            $segs['ArrCode'] = $m[2];
        }
        $date = $this->pdf->FindSingleNode("(//p[contains(.,'" . $this->t('Date') . ":')]/following::b[1])[1]");
        $timeDep = $this->pdf->FindSingleNode("(//p[contains(.,'" . $this->t('Departs') . ":')]/following::b[1])[1]");
        $timeArr = $this->pdf->FindSingleNode("(//p[contains(.,'" . $this->t('Arrives') . ":')]/following::b[1])[1]");
        $segs['DepDate'] = strtotime($date . " " . trim(str_replace("M", "", $timeDep)) . "M");
        $segs['ArrDate'] = strtotime($date . " " . trim(str_replace("M", "", $timeArr)) . "M");
        $segs['Seats'] = implode(",", $this->pdf->FindNodes("//p[contains(.,'" . $this->t('SEAT') . ":')]/following::b[1]"));

        $it['TripSegments'][] = $segs;

        return [$it];
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

                    break;
                }
            }
        }

        return true;
    }
}
