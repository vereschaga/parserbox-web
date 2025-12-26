<?php

namespace AwardWallet\Engine\yatra\Email;

class ETicketPDF extends \TAccountChecker
{
    public $mailFiles = "yatra/it-6836713.eml, yatra/it-9871694.eml";

    public $reFrom = "yatra.com";
    public $reBody = [
        'en' => ['Download Yatra App', 'E-TICKET', 'FLIGHT'],
    ];
    public $reSubject = [
        'Your Yatra Document(s)',
    ];
    public $lang = '';
    /** @var \HttpBrowser */
    public $pdf;
    public $pdfNamePattern = "(?:ETicket|YT|.*Flight\W*Ticktes|.*Flight\W*Tickets|\d+\-ticket).*pdf";
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
//        if (count($pdfs) === 0)
//            $pdfs = $parser->searchAttachmentByName('.*pdf');
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
            'emailType'  => "ETicketPDF" . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
//        if (count($pdfs) === 0)
//            $pdfs = $parser->searchAttachmentByName('.*pdf');
        if (isset($pdfs[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]));
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

    public function findСutSection($input, $searchStart, $searchFinish)
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

    private function parseEmail($textFull)
    {
        $docs = array_filter(explode('---DOCUMENTENDS---', $textFull));
        $pax = [];
        $ticketNumbers = [];
        $nodes = [];

        foreach ($docs as $text) {
            $textFlights = $this->findСutSection($text, null, 'PASSENGERS DETAILS');
            $tripNum = $this->re("#YATRA REF NUMBER\s+([A-Z\d]+)#", $textFlights);
            $textPax = $this->findСutSection($text, 'PASSENGERS DETAILS', 'Information');

            if (preg_match_all("/\n\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s+\(?(?:Adult|Chil).+\n\s*[A-Z]{3}[\s-]+/u", $textPax, $m)) {
                $pax = array_merge($pax, $m[1]);
            }

            if (strpos($textFlights, 'PNR') === false) {
                $textFlights = $this->findСutSection($text, 'PASSENGERS DETAILS', 'Information');
                $tripNum = $this->re("#YATRA REF NUMBER\s+([A-Z\d]+)#", $text);
                $textPax = $this->findСutSection($text, 'PASSENGERS DETAILS', 'PNR');

                if (preg_match_all("/\n\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s+\(?(?:Adult|Chil).+\n\s*[A-Z]{3}[\s-]+/u", $textPax, $m)
                    || preg_match_all("/\n\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s+[A-Z]{3}\s*-\s*[A-Z]{3}\s+(.*\n){1,4}\s*\(?(?:Adult|Chil)/u", $textPax, $m)
                ) {
                    $pax = array_merge($pax, $m[1]);
                }

                if (preg_match_all("/^(\d{3}[- ]*\d{5,}[- ]*\d{1,2})$/m", $textPax, $m)) {
                    $ticketNumbers = array_merge($ticketNumbers, $m[1]);
                }
            }
            $nodes = array_merge($nodes, $this->splitter("#^PNR\n(.+)#m", $textFlights));
        }

        $its = [];
        $airs = [];

        foreach ($nodes as $node) {
            if (preg_match("#(?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\-\s*(?<FlightNumber>\d+)\n#", $node, $m)) {
                $roots = $this->pdf->XPath->query("//p[contains(.,'{$m['AirlineName']}') and contains(.,'{$m['FlightNumber']}')]");
            } else {
                $roots = [];
            }
            $rl = $this->re("#^([A-Z\d]{5,})#m", $node);

            if ($rl) {
                $airs[$rl][] = ['text' => $node, 'nodes' => $roots];
            } elseif ($tripNum) {
                $airs[$tripNum][] = ['text' => $node, 'nodes' => $roots];
            }
        }

        foreach ($airs as $rl => $segments) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $rl;
            $it['TripNumber'] = $tripNum;

            if (count($pax)) {
                $it['Passengers'] = array_values(array_unique(array_filter($pax)));
            }

            if (count($ticketNumbers)) {
                $it['TicketNumbers'] = array_unique($ticketNumbers);
            }

            foreach ($segments as $node) {
                $seg = [];

                if (preg_match("#^\s*(.+)\n\s*(.+)#", $node['text'], $m)) {
                    $seg['DepName'] = $m[1];
                    $seg['ArrName'] = $m[2];
                }
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

                if (count($node['nodes']) > 0) {
                    $root = $node['nodes']->item(0);

                    $str = implode(' ', $this->pdf->FindNodes("preceding::p[normalize-space()][position()<3]/descendant::text()", $root));

                    if (preg_match("/\b((?:\d{1,3} ?hr )?\d{1,3} ?min)\b/i", $str, $m)) {
                        // 05hr 48min
                        $seg['Duration'] = $m[1];
                    }

                    $str = $this->pdf->FindSingleNode('.', $root);

                    if (preg_match("/(?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*-\s*(?<FlightNumber>\d+)/", $str, $m)) {
                        $seg['AirlineName'] = $m['AirlineName'];
                        $seg['FlightNumber'] = $m['FlightNumber'];
                    }

                    $posDown = 1;

                    $str = implode(' ', $this->pdf->FindNodes("following::p[normalize-space()][{$posDown}]/descendant::text()", $root));

                    if (preg_match("/Operated By\s*(.*)/i", $str, $m)) {
                        $seg['Operator'] = rtrim($m[1], ' )');
                        $posDown++;
                        $str = implode(' ', $this->pdf->FindNodes("following::p[normalize-space()][{$posDown}]/descendant::text()", $root));
                    }

                    $seg['DepDate'] = strtotime($this->normalizeDate($str));
                    $posDown++;
                    $str = implode(' ', $this->pdf->FindNodes("following::p[normalize-space()][{$posDown}]/descendant::text()", $root));
                    $seg['ArrDate'] = strtotime($this->normalizeDate($str));

                    $posDown++;
                    $stops = $this->pdf->FindSingleNode("following::p[normalize-space()][{$posDown}]", $root);

                    if (preg_match("/^(\d{1,3})\s*Stop/i", $stops, $m)) {
                        $seg['Stops'] = $m[1];
                    } elseif (preg_match("/^Non[-\s]*Stop/i", $stops)) {
                        $seg['Stops'] = 0;
                    }
                    $posDown++;
                    $seg['DepName'] .= ', ' . $this->pdf->FindSingleNode("following::p[normalize-space()][{$posDown}]", $root);
                    $posDown++;
                    $seg['ArrName'] .= ', ' . $this->pdf->FindSingleNode("following::p[normalize-space()][{$posDown}]", $root);
                }

                // Toronto, Lester B. Pearson Intl, T-1
                $patterns['nameTerminal'] = '/^(.+?)[,\s]+T-([A-Z\d ]+)$/';

                if (!empty($seg['DepName']) && preg_match($patterns['nameTerminal'], $seg['DepName'], $m)) {
                    $seg['DepName'] = $m[1];
                    $seg['DepartureTerminal'] = $m[2];
                }

                if (!empty($seg['ArrName']) && preg_match($patterns['nameTerminal'], $seg['ArrName'], $m)) {
                    $seg['ArrName'] = $m[1];
                    $seg['ArrivalTerminal'] = $m[2];
                }

                if (empty($seg['DepDate'])
                    && preg_match("#^([A-Z\d]{5,})\s+(?<airline>.+)\n(?<depDate1>.+)\n(?<arrDate1>.+)\n(?<stops>.+)\n(?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])[\s\-]+(?<FlightNumber>\d+)\n(?<depDate2>.+)\n(?<arrDate2>.+)\n#m", $node['text'], $m)
                ) {
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
            '#^\s*\S+\s+([[:alpha:]]{3,})\s+(\d{1,2})\s+(\d{2,4})\s+(\d{1,2}) ?[:]+ ?(\d{2})\s*HRS?\s*$#iu',
        ];
        $out = [
            '$2 $1 $3 $4:$5',
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
