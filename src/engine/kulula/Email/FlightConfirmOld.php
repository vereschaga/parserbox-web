<?php

namespace AwardWallet\Engine\kulula\Email;

class FlightConfirmOld extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "kulula/it-4184064.eml";
    public $reBody = [
        'en' => ['prepared', 'for'],
    ];
    public $pdf;
    public $lang = '';
    public static $dict = [
        'en' => [
            'Record locator'  => 'kulula booking code',
            'Passenger'       => 'travellers',
            'Date of booking' => 'Invoice Date',
            'Ticket'          => 'Tax. Amount',
            'Total cost'      => 'total additional',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

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

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }
        $its = $this->parseEmail();

        return [
            'parsedData' => [
                'Itineraries' => $its,
            ],
            'emailType' => 'FlightConfirmOld',
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return preg_match('/Thank\s+you\s+for\s+choosing\s+to\s+book\s+with\s+kulula\.com/i', $this->http->Response['body'])
            && $this->http->XPath->query('//a[contains(@href,"//www.kulula.com")]')->length > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && stripos($headers['subject'], 'kulula flight') !== false
            || isset($headers['from']) && stripos($headers['from'], 'no-reply@flights.kulula.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'kulula.com') !== false;
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

        $it['RecordLocator'] = $this->http->FindSingleNode("//span[contains(translate(.,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'" . $this->t('Record locator') . "')]/following::span[1]");

        $it['Passengers'] = $this->http->FindNodes("//a[contains(@href,'eticket.html?pnr')]/../../td[1]");
//
//        $tmpCostCurr_ = $this->http->FindSingleNode("//div[contains(text(),'" .$this->t('Total cost'). "')]/descendant::text()/following::text()[normalize-space(.)!=''][1]");
//        if( preg_match("#(.+)\s+(.+)#", $tmpCostCurr_, $m) ){
//            $it['TotalCharge'] = cost((strpos($m[2],'.')!==FALSE)?$m[2]:$m[2].'.0');
//            $it['Currency'] = currency($m[1]);
//        }
        $segs = [];

        $flightNum = $this->http->FindNodes("//td[@style='width:93px;padding:8px 6px 8px 6px;vertical-align:top']/../../tr[not(td[@style='border-top: 1px solid #e8e8e8;'])]/td[4]");
        $depArr = $this->http->FindNodes("//td[@style='width:93px;padding:8px 6px 8px 6px;vertical-align:top']/../../tr[not(td[@style='border-top: 1px solid #e8e8e8;'])]/td[2]");
        $arrArr = $this->http->FindNodes("//td[@style='width:93px;padding:8px 6px 8px 6px;vertical-align:top']/../../tr[not(td[@style='border-top: 1px solid #e8e8e8;'])]/td[3]");
        $dateDepArr = $this->http->FindNodes("//td[@style='width:93px;padding:8px 6px 8px 6px;vertical-align:top']/../../tr[not(td[@style='border-top: 1px solid #e8e8e8;'])]/td[1]");

        for ($i = 0; $i < count($flightNum); $i++) {
            $m = explode(" ", $flightNum[$i]);
            $segs[$i]['AirlineName'] = trim($m[0]);
            $segs[$i]['FlightNumber'] = trim($m[1]);

            $segs[$i]['Seats'] = implode(',', $this->http->FindNodes("//a[contains(@href,'eticket.html?pnr')]/../../td[3]"));

            if (preg_match('#(.+)\s([0-2]?[0-9]+\:[0-5][0-9]\s*[PA]?[M]?)\s(.+)#', trim($depArr[$i]), $m)) {
                $segs[$i]['DepName'] = trim($m[1]);
                $nodes = $this->pdf->FindNodes("//p[contains(translate(.,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),\"" . strtolower(trim($m[1])) . "\") and not(b)]/preceding-sibling::*[1]");
                $segs[$i]['DepCode'] = ($nodes == null) ? TRIP_CODE_UNKNOWN : trim($nodes[1]);
                $segs[$i]['DepDate'] = strtotime($dateDepArr[$i] . ' ' . trim($m[2]));
                $segs[$i]['DepartureTerminal'] = trim(str_replace("TERMINAL", "", $m[3]));
            }

            if (preg_match('#(.+)\s([0-2]?[0-9]+\:[0-5][0-9]\s*[PA]?[M]?)#', trim($arrArr[$i]), $m)) {
                $segs[$i]['ArrName'] = trim($m[1]);
                $nodes = $this->pdf->FindNodes("//p[contains(translate(.,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),\"" . strtolower(trim($m[1])) . "\") and not(b)]/preceding-sibling::*[1]");
                $segs[$i]['ArrCode'] = ($nodes == null) ? TRIP_CODE_UNKNOWN : trim($nodes[1]);
                $segs[$i]['ArrDate'] = strtotime($dateDepArr[$i] . ' ' . trim($m[2]));
            }
        }

        for ($i = 0; $i < count($flightNum); $i++) {
            $it['TripSegments'][$i] = $segs[$i];
        }

        return [$it];
    }

    /**
     * example: 27-Sep-13, 27.09.13.
     *
     * @param $date
     *
     * @return mixed
     */
    private function normalizeDate($date)
    {
        $in = [
            '#[\S\s]*(\d{2})[\.\/]*(\d{2})[\.\/]*(\d{2})#',
            '#[\S\s]*(\d{2})-(\D{3,})-(\d{2})[.]*#',
        ];
        $out = [
            '$2/$1/$3',
            '$2 $1 $3',
        ];

        return preg_replace($in, $out, $date);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
