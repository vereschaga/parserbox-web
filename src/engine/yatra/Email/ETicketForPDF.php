<?php

namespace AwardWallet\Engine\yatra\Email;

class ETicketForPDF extends \TAccountChecker
{
    public $mailFiles = "yatra/it-9900680.eml, yatra/it-9900762.eml";

    public $reFrom = "yatra.com";
    public $reBody = [
        'en' => ['Use your Airline PNR for all', 'Flight e-Ticket'],
    ];
    public $reSubject = [
        'Your Yatra Document(s)',
    ];
    public $lang = '';

    /** @var \HttpBrowser $pdf */
    public $pdf;
    public $pdfNamePattern = "(?:ETicket|YT).*pdf";

    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $text = '';

            foreach ($pdfs as $pdf) {
                if (($text .= text(\PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE))) !== null) {
                    $this->pdf->SetEmailBody(\PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX));
                    $text .= '---DOCUMENTENDS---';
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }
        $NBSP = chr(194) . chr(160);
        $text = str_replace($NBSP, ' ', html_entity_decode($text));
        $this->AssignLang($text);

        $its = $this->parseEmail($text);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "ETicketForPDF" . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));
            $NBSP = chr(194) . chr(160);
            $text = str_replace($NBSP, ' ', html_entity_decode($text));

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

    public function findCutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;

        if (empty($searchStart)) {
            $left = $input;
        } else {
            $left = mb_strstr($input, $searchStart);
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($left, $searchFinish, true);
        } else {
            $inputResult = $left;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
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

    private function parseEmail($texts)
    {
        $tripNum = $this->re("#Booking Reference Number[\-\s]+([A-Z\d]+)#", $texts);
        $resDate = strtotime($this->re("#Booking Date[\-\s]+(.+)#", $texts));

        $docs = array_filter(explode('Flight Details', $texts));

        $tickets = [];
        $pax = [];
        $nodes = [];

        if (count($docs) > 0) {
            array_shift($docs);
        }

        foreach ($docs as $text) {
            $textFlights = strstr($text, 'Passenger Details', true);
            $nodes = array_merge($nodes, $this->splitter("#^(.+\n.+\nAirline PNR)#m", $textFlights));

            $textPax = strstr($text, 'Passenger Details');

            if (preg_match_all("#Name\s+(.+?) *(?:\(|\n)#", $textPax, $m)) {
                $pax = array_merge($pax, $m[1]);
            }

            if (preg_match_all("#Ticket No\.\s+(.+?) *(?:PNR|\n)#", $textPax, $m)) {
                $tickets = array_merge($tickets, $m[1]);
            }
        }
        $pax = array_values(array_unique($pax));
        $tickets = array_values(array_unique($tickets));

        $its = [];
        $airs = [];

        foreach ($nodes as $node) {
            if (preg_match("#(?<AirlineName>[A-Z\d]{2})\s*\-.*?\d+:\d+\n\d+:\d+\n.+?\s*(?<FlightNumber>\d+)\n#s", $node, $m)
                || preg_match("#(?<AirlineName>[A-Z\d]{2})\s*\-.*?.+?\s*(?:(?!\-)[\w\s])(?<FlightNumber>\d+)\n#s", $node, $m)
            ) {
                $root = $this->pdf->XPath->query("//p[contains(.,'{$m['AirlineName']}') and ./following::p[position()=1 or position()=2][contains(.,'{$m['FlightNumber']}')]]");
            } else {
                $root = [];
            }
            $rl = $this->re("#Airline PNR[:\s]+([A-Z\d]+)#", $node);

            if ($rl) {
                $airs[$rl][] = [$node, $root];
            } else {
                $airs[$tripNum][] = [$node, $root];
            }
        }

        foreach ($airs as $rl => $nodes) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $rl;
            $it['TripNumber'] = $tripNum;
            $it['ReservationDate'] = $resDate;

            if (isset($pax)) {
                $it['Passengers'] = $pax;
            }

            if (isset($tickets)) {
                $it['TicketNumbers'] = $tickets;
            }

            foreach ($nodes as $node) {
                $seg = [];

                if (preg_match("#^\s*(.+)\n\s*(.+)#", $node[0], $m)) {
                    $seg['DepName'] = $m[1];
                    $seg['ArrName'] = $m[2];
                }

                $seg['Cabin'] = $this->re("#(Business|Economy|First class)(?:\s+class)?#i", $node[0]);
                $seg['Duration'] = $this->re('/Duration\s*:\s*(\d{1,2}\s*[a-z]+\s*\d{1,2}[a-z]+)/', $node[0]);
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

                if (count($node[1]) > 0) {
                    $str = implode(" ", $this->pdf->FindNodes(". | ./following::p[position()=1 or position()=2]", $node[1]->item(0)));

                    if (preg_match("#(?<AirlineName>[A-Z\d]{2})\s*\-.+?\s*(?<FlightNumber>\d+)#s", $str, $m)) {
                        $seg['AirlineName'] = $m['AirlineName'];
                        $seg['FlightNumber'] = $m['FlightNumber'];
                    }
                    $str = implode(" ", $this->pdf->FindNodes("./following-sibling::p[normalize-space(.)!=''][position()=3 or position()=4]//text()", $node[1]->item(0)));
                    $seg['DepDate'] = strtotime($this->normalizeDate($str));
                    $str = implode(" ", $this->pdf->FindNodes("./following-sibling::p[normalize-space(.)!=''][position()=6 or position()=7]//text()", $node[1]->item(0)));
                    $seg['ArrDate'] = strtotime($this->normalizeDate($str));

                    if (!$seg['DepDate'] && !$seg['ArrDate']) {
                        $str = implode(" ", $this->pdf->FindNodes("(./following-sibling::p[normalize-space(.)!=''][position()=3]//text())[last()]", $node[1]->item(0)));
                        $seg['DepDate'] = strtotime($this->normalizeDate($str));
                        $str = implode(" ", $this->pdf->FindNodes("(./following-sibling::p[normalize-space(.)!=''][position()=4]//text())[last()]", $node[1]->item(0)));
                        $seg['ArrDate'] = strtotime($this->normalizeDate($str));
                    }

                    if ($seg['ArrDate'] < $seg['DepDate']) {
                        $seg['ArrDate'] = strtotime("+1 day", $seg['ArrDate']);
                    }
                }

                if (!isset($seg['DepDate']) && preg_match("#^([A-Z\d]{5,})\s+(?<airline>.+)\n(?<depDate1>.+)\n(?<arrDate1>.+)\n(?<stops>.+)\n(?<AirlineName>[A-Z\d]{2})[\s\-]+(?<FlightNumber>\d+)\n(?<depDate2>.+)\n(?<arrDate2>.+)\n#m", $node[0], $m)) {
                    $seg['DepDate'] = strtotime($this->normalizeDate($m['depDate1'] . ' ' . $m['depDate2']));
                    $seg['ArrDate'] = strtotime($this->normalizeDate($m['arrDate1'] . ' ' . $m['arrDate2']));
                    $seg['AirlineName'] = $m['AirlineName'];
                    $seg['FlightNumber'] = $m['FlightNumber'];
                }
                $it['TripSegments'][] = $seg;
            }
            $its[] = $it;
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        $in = [
            // Tue, Jun 6 2017  22:15 Hrs
            '#^\s*\S+\s+(\w+)\s+(\d+)\s+(\d+)\s+(\d+:\d+)\s*HRS?\s*$#i',
        ];
        $out = [
            '$2 $1 $3 $4',
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
