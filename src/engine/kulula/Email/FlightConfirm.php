<?php

namespace AwardWallet\Engine\kulula\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;

// TODO: merge with parsers tcase/It5045494, velocity/ETicket2 (in favor of tcase/It5045494)

class FlightConfirm extends \TAccountChecker
{
    public $mailFiles = "kulula/it-4355438.eml, kulula/it-4355439.eml";

    public $reBody = [
        'en' => ['operated', 'aircraft'],
    ];

    public $lang = '';

    public static $dict = [
        'en' => [
            'Record locator'      => 'reservation code',
            'airline reservation' => 'airlinereservationcode',
            'Passenger'           => 'travellers',
            'Date of booking'     => 'Invoice Date',
            'Ticket'              => 'Tax. Amount',
            'Total cost'          => 'additional',
        ],
    ];

    /** @var \HttpBrowser */
    private $pdf;

    private $dateFromHeader = 0;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];

        $this->dateFromHeader = EmailDateHelper::calculateOriginalDate($this, $parser);

        $body = $parser->getHTMLBody();

        foreach ($this->reBody as $lang => $reBody) {
            if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $html = '';

            foreach ($pdfs as $pdf) {
                if (($html .= \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) === null) {
                    return null;
                }
            }
            $this->pdf->SetBody($html);
            $its = $this->parseEmail();
        }

        return [
            'parsedData' => [
                'Itineraries' => $its,
            ],
            'emailType' => 'FlightConfirm' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return (
            stripos($body, 'Thank you for choosing to book with kulula.com') !== false
            || stripos($body, 'See you onboard') !== false
        )
            && (
            $this->http->XPath->query('//a[contains(@href,"//www.kulula.com")]')->length > 0
            || stripos($body, 'kulula.com') !== false
        );
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

    protected function orval(...$arr)
    {
        foreach ($arr as $item) {
            if (!empty($item)) {
                return $item;
            }
        }

        return null;
    }

    private function parseEmail()
    {
        $NBSP = chr(194) . chr(160);

        $it = ['Kind' => 'T', 'TripSegments' => []];

        $regNumber = $this->http->FindSingleNode("//span[contains(@id,'confirmation-no')]", null, true, '/\s+(\w+)/');

        if ($regNumber != null) {
            $it['RecordLocator'] = $regNumber;
        } else {
            $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(translate(.,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'" . $this->t('Record locator') . "')]/following::text()[normalize-space(.)!=''][1]", null, true, '/(\w+)/');
        }

        $pasngrAndTickets = $this->http->FindSingleNode("//td[contains(normalize-space(.), 'Your ticket(s) is/are') and not(.//td)]");
        preg_match_all("/([a-z\s]+)\s*:\s*(\d+)/i", $pasngrAndTickets, $m);

        if (!empty($m[1]) && !empty($m[2])) {
            $it['Passengers'] = array_filter(array_unique(array_map("trim", $m[1])));
            $it['TicketNumbers'] = array_filter(array_unique(array_map("trim", $m[2])));
        }

        $segs = [];

        $flightNum = $this->http->FindNodes("//td[contains(., 'Flight Number') and not(.//td)]");

        $depArr = $this->getNode('Departure', '/(.+)\s+\d+:\d+/');

        $arrArr = $this->getNode('Arrival', '/(.+)\s+\d+:\d+/');

        $aircraft = $this->getNode('Aircraft');

        $class = $this->getNode('Class');

        $meal = $this->getNode('Meal');

        $duration = $this->getNode('Duration');

        $distance = $this->getNode('Distance');

        $depTime = $this->getNode('Departure', '/(\d+:\d+)/');

        $arrTime = $this->getNode('Arrival', '/(\d+:\d+)/');

        // 24 AUG 2016   24 AUG 2016 
        $year = $this->pdf->FindSingleNode("//text()[contains(translate(translate(.,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),' " . $NBSP . "',''), 'tripto')]/preceding::*[normalize-space(.)!=''][1]", null, true, '/\w+\s+(\d{4})\s+\d+/');
        $dateDepArr = $this->pdf->FindNodes("//text()[contains(normalize-space(.), 'DEPARTURE:')]/following::*[normalize-space(.)!=''][1]", null, '/\w+ (\d+ \w+)/');

        $re = '/(?:(?<aname1>[A-Z]{2})\s+Flight\s+Number\s+(?<fnum1>\d+)|(?<aname2>[A-Z]{2})\s+(?<fnum2>\d+))\s*Operated by:\s+(?<oper>.+)\s*(?<conf>confirmed|cancelled)/i';
        $status = '';

        for ($i = 0; $i < count($flightNum); $i++) {
            if (isset($flightNum[$i]) && preg_match($re, $flightNum[$i], $m)) {
                $segs[$i]['AirlineName'] = $this->orval($m['aname1'], $m['aname2']);
                $segs[$i]['FlightNumber'] = $this->orval($m['fnum1'], $m['fnum2']);
                $segs[$i]['Operator'] = $m['oper'];
                $status = $m['conf'];
            } else {
                $segs[$i]['s'] = $flightNum[$i];
            }

//            DUR King Shaka (Durbs)
            if (isset($depArr[$i]) && preg_match('#([A-Z]{3})\s+(.+)#', $depArr[$i], $m)) {
                $segs[$i]['DepCode'] = trim($m[1]);
                $segs[$i]['DepName'] = trim($m[2]);
            }

            if (isset($arrArr[$i]) && preg_match('#([A-Z]{3})\s+(.+)#', $arrArr[$i], $m)) {
                $segs[$i]['ArrCode'] = trim($m[1]);
                $segs[$i]['ArrName'] = trim($m[2]);
            }

            if (isset($dateDepArr[$i]) && preg_match('#(\d+\s+[A-Z]{3}\s+\d+)#', $dateDepArr[$i] . ' ' . $year, $m)) {
                $segs[$i]['DepDate'] = strtotime($this->normalizeDate($m[1] . ' ' . $depTime[$i]));
                $segs[$i]['ArrDate'] = strtotime($this->normalizeDate($m[1] . ' ' . $arrTime[$i]));
            }

            if (isset($aircraft[$i])) {
                $segs[$i]['Aircraft'] = $aircraft[$i];
            }

            if (isset($class[$i])) {
                $segs[$i]['Cabin'] = $class[$i];
            }

            if ($meal && isset($meal[$i])) {
                $segs[$i]['Meal'] = $meal[$i];
            }

            if (isset($duration[$i])) {
                $segs[$i]['Duration'] = $duration[$i];
            }

            if (isset($distance[$i])) {
                $segs[$i]['TraveledMiles'] = $distance[$i];
            }
        }

        if (!empty($status)) {
            $it['Status'] = $status;
        }

        for ($i = 0; $i < count($flightNum); $i++) {
            $it['TripSegments'][$i] = $segs[$i];
        }

        return [$it];
    }

    private function getNode($str, $re = null, $one = false)
    {
        if (!$one) {
            return $this->http->FindNodes("//td[contains(., '{$str}') and not(.//td)]/following-sibling::td[1]", null, $re);
        } else {
            return $this->http->FindSingleNode("(//td[contains(., '{$str}') and not(.//td)]/following-sibling::td[1])[1]", null, $re);
        }
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
