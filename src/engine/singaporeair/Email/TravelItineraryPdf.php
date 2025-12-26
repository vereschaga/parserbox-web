<?php

namespace AwardWallet\Engine\singaporeair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;

class TravelItineraryPdf extends \TAccountChecker
{
    public $mailFiles = "singaporeair/it-11793923.eml, singaporeair/it-11836628.eml, singaporeair/it-270009046.eml, singaporeair/it-727961126.eml";

    protected $langDetectorsPdf = [
        'en' => ['BOOKING REFERENCE:', 'Booking Reference :'],
    ];
    protected $lang = '';
    protected static $dict = [
        'en' => [
            'Departs'                        => ['Departs', 'Depart'],
            'Arrives'                        => ['Arrives', 'Arrive'],
        ],
    ];

    private $date;

    // Standard Methods

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("(//table[thead[{$this->contains($this->t('Departs'))} and {$this->contains($this->t('Arrives'))}]]//tr[td[@colspan=4 and not({$this->contains($this->t('You were originally booked on:'))})]])")->length > 0
        || $this->http->XPath->query("//text()[normalize-space()='Flight']/ancestor::tr[1][contains(normalize-space(), 'Depart') and contains(normalize-space(), 'Arrive')]")->length > 0) {
            return false;
        }

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($textPdf, 'on board Singapore Airline') === false && stripos($textPdf, 'local Singapore Airline') === false && stripos($textPdf, 'Reserved. Singapore Co') === false) {
                continue;
            }

            if ($this->assignLangPdf($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $textPdfFull = '';

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->assignLangPdf($textPdf)) {
                $textPdfFull .= $textPdf;
            }
        }

        if (!$textPdfFull && count($pdfs) > 0) {
            $this->logger->notice("Can't determine a language!");

            return false;
        }

        $its = $this->parsePdf($textPdfFull);

        return [
            'parsedData' => [
                'Itineraries' => $its,
            ],
            'emailType' => 'TravelItineraryPdf' . ucfirst($this->lang),
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function parsePdf($text)
    {
        $its = [];

        $itineraries = $this->splitText($text, '/^[ ]*(Dear[ ]+\w[-,.\'\w ]*\w(?:[ ]{2}|$)(?:.*\n){1,5}.* BOOKING REFERENCE|.* BOOKING REFERENCE:.+(?:.*\n){1,5} *Dear[ ]+\w+)/miu', true);

        foreach ($itineraries as $itinerary) {
            if (stripos($itinerary, 'DEPARTING') == false) {
                continue;
            }

            $itFlight = $this->parseItinerary($itinerary);

            if ($itFlight === false) {
                continue;
            }

            if (($key = $this->recordLocatorInArray($itFlight['RecordLocator'], $its)) !== false) {
                if (!empty($itFlight['Passengers'][0])) {
                    if (!empty($its[$key]['Passengers'][0])) {
                        $its[$key]['Passengers'] = array_merge($its[$key]['Passengers'], $itFlight['Passengers']);
                        $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
                    } else {
                        $its[$key]['Passengers'] = $itFlight['Passengers'];
                    }
                }

                if (!empty($itFlight['TicketNumbers'][0])) {
                    if (!empty($its[$key]['TicketNumbers'][0])) {
                        $its[$key]['TicketNumbers'] = array_merge($its[$key]['TicketNumbers'], $itFlight['TicketNumbers']);
                        $its[$key]['TicketNumbers'] = array_unique($its[$key]['TicketNumbers']);
                    } else {
                        $its[$key]['TicketNumbers'] = $itFlight['TicketNumbers'];
                    }
                }
                $its[$key]['TripSegments'] = array_merge($its[$key]['TripSegments'], $itFlight['TripSegments']);

                if (!empty($itFlight['SpentAwards'])) {
                    if (!empty($its[$key]['SpentAwards'])) {
                        $patternAwards = '/^(\d[,\d ]*?)[ ]*([A-z][A-z ]*\b)$/i';

                        if (preg_match($patternAwards, $itFlight['SpentAwards'], $n) && preg_match($patternAwards, $its[$key]['SpentAwards'], $m)) {
                            if ($n[2] === $m[2]) {
                                $its[$key]['SpentAwards'] = $this->normalizePrice($n[1]) + $this->normalizePrice($m[1]) . ' ' . $n[2];
                            } else {
                                unset($its[$key]['SpentAwards']);
                            }
                        }
                    }
                }

                if (!empty($itFlight['Currency']) && isset($itFlight['TotalCharge']) && $itFlight['TotalCharge'] !== null) {
                    if (!empty($its[$key]['Currency']) && $its[$key]['TotalCharge'] !== null) {
                        if ($itFlight['Currency'] === $its[$key]['Currency']) {
                            $its[$key]['TotalCharge'] += $itFlight['TotalCharge'];
                        } else {
                            unset($its[$key]['Currency'], $its[$key]['TotalCharge']);
                        }
                    }
                }

                if (!empty($itFlight['Currency']) && isset($itFlight['BaseFare']) && $itFlight['BaseFare'] !== null) {
                    if (!empty($its[$key]['Currency']) && isset($its[$key]['BaseFare']) && $its[$key]['BaseFare'] !== null) {
                        if ($itFlight['Currency'] === $its[$key]['Currency']) {
                            $its[$key]['BaseFare'] += $itFlight['BaseFare'];
                        } else {
                            unset($its[$key]['Currency'], $its[$key]['BaseFare']);
                        }
                    }
                }

                if (!empty($itFlight['Fees'])) {
                    if (isset($its[$key]['Fees'])) {
                        $its[$key]['Fees'] = array_merge($its[$key]['Fees'], $itFlight['Fees']);
                    } else {
                        $its[$key]['Fees'] = $itFlight['Fees'];
                    }
                }
            } else {
                $its[] = $itFlight;
            }
        }

        foreach ($its as $key => $it) {
            $its[$key] = $this->uniqueTripSegments($it);
        }

        return $its;
    }

    protected function parseItinerary($text)
    {
        $patterns = [
            'code' => '[A-Z]{3}', // SIN
            'time' => '\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?', // 22:01
            'date' => '\d{1,2}\s*[^-,.\d\s\/]{3,}\s*\d{4}', // 30 Mar 2018
        ];

        $it = [];
        $it['Kind'] = 'T';

        // Passengers
        if (preg_match('/^[ ]*Dear[ ]+(\w[-,.\'\w ]*\w)(?:[ ]{2}|$)/miu', $text, $matches)) {
            $it['Passengers'] = [preg_replace("/^\s*(?:Mrs|Mr|Ms|Miss|Mstr)\s+/u", '', $matches[1])];
        }

        // RecordLocator
        if (preg_match('/BOOKING REFERENCE[ ]*:[ ]*([A-Z\d]{5,})(?:$|[ ]{2,})/mi', $text, $matches)) {
            $it['RecordLocator'] = $matches[1];
        }

        // TicketNumbers
        if (preg_match('/Electronic ticket[ ]*:[ ]*(\d[- \d]{6,}\d)(?:$|[ ]{2,})/mi', $text, $matches)) {
            $it['TicketNumbers'] = [$matches[1]];
        }

        // ReservationDate
        if (preg_match('/Date of Issue[ ]*:[ ]*(' . $patterns['date'] . ')(?:$|[ ]{2,})/mi', $text, $matches)) {
            $it['ReservationDate'] = strtotime($matches[1]);
        }

        // TripSegments
        $it['TripSegments'] = [];
        $segments = $this->splitText($text, '/^[ ]*\d{1,2}[ ]*\.[ ]*([A-Z\d]{2}\d+)\b/m', true);

        foreach ($segments as $segment) {
            $segmentText = $this->re("/\n+(^\s*DEPARTING.+)/ms", $segment);
            $segmentTable = $this->SplitCols($segmentText);

            $depTerminal = $this->re("/Terminal\s*(\S+)/s", $segmentTable[0]);
            $arrTerminal = $this->re("/Terminal\s*(\S+)/s", $segmentTable[1]);

            $seg = [];

            // AirlineName
            // FlightNumber
            // Cabin
            // BookingClass
            if (preg_match('/^([A-Z\d]{2})(\d+)\b(?:.+[ ]{2,}(\w[\w )(]*))?/', $segment, $matches)) {
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = $matches[2];

                if (preg_match("/$matches[1]$matches[2]\n\s*\D+[ ]{10,}\D+[ ]{10,}(?<aircraft>.+)\n/u", $segment, $m)) {
                    $seg['Aircraft'] = $m['aircraft'];
                }

                if (preg_match('/^(\w[\w ]*?)[ ]*\(([A-Z]{1,2})\)$/', $matches[3], $m)) {
                    // ECONOMY (N)
                    $seg['Cabin'] = $m[1];
                    $seg['BookingClass'] = $m[2];
                } elseif (preg_match('/^(\w[\w ]+)$/', $matches[3], $m)) {
                    // ECONOMY
                    $seg['Cabin'] = $m[1];
                }
            }

            // Stops
            // Duration
            if (preg_match('/^[ ]*(.{4,})$\s+^[ ]*(?:DEPARTING|ARRIVING)\b/mi', $segment, $matches)) {
                // Non-stop • 2hrs 30mins
                if (preg_match('/Non[- ]*stop/i', $matches[1])) {
                    $seg['Stops'] = 0;
                } elseif (preg_match('/\b(\d{1,3})[- ]*stop/i', $matches[1], $m)) {
                    $seg['Stops'] = $m[1];
                }

                if (preg_match('/\b(\d[\d hrsmin]+)\b/i', $matches[1], $m)) {
                    $seg['Duration'] = $m[1];
                }
            }

            // DepCode
            // ArrCode
            if (preg_match('/(' . $patterns['code'] . ')[ ]*(' . $patterns['time'] . ')[ ]{2,}(' . $patterns['code'] . ')[ ]*(' . $patterns['time'] . ')/', $segment, $matches)) {
                // SIN 17:30    BKK 19:00
                $seg['DepCode'] = $matches[1];
                $timeDep = $matches[2];
                $seg['ArrCode'] = $matches[3];
                $timeArr = $matches[4];

                if (!empty($depTerminal)) {
                    $seg['DepartureTerminal'] = $depTerminal;
                }

                if (!empty($arrTerminal)) {
                    $seg['ArrivalTerminal'] = $arrTerminal;
                }
            }

            // DepDate
            // ArrDate
            if (preg_match('/(' . $patterns['date'] . ').+?(' . $patterns['date'] . ')/', $segment, $matches)
            || preg_match('/(' . $patterns['date'] . ').+?(\d{1,2}\s*[^-,.\d\s\/]{3,})/', $segment, $matches)) {
                $dateDep = $matches[1];

                $year = $this->re("/\s(\d{4})/", $matches[1]);

                if (!preg_match("/\d{4}\s*$/", $matches[2])) {
                    $matches[2] = $matches[2] . ' ' . $year;
                }

                $dateArr = $matches[2];
            }

            if (isset($dateDep) && isset($dateArr) && isset($timeDep) && isset($timeArr)) {
                $seg['DepDate'] = strtotime($dateDep . ', ' . $timeDep);
                $seg['ArrDate'] = strtotime($dateArr . ', ' . $timeArr);
            }

            $it['TripSegments'][] = $seg;
        }

        // SpentAwards
        // Currency
        // TotalCharge
        $paymentStart = stripos($text, "Payment details\n");

        if ($paymentStart !== false) {
            $payment = substr($text, $paymentStart);
        } else {
            $payment = $text;
        }

        if (preg_match('/.+\n([\s\S]+?)\n.+[ ]{3,}(Grand Total.+)/i', $payment, $m)) {
            $headPos = $this->TableHeadPos($this->inOneRow($m[1]));

            if (count($headPos) == 3) {
                $table = $this->SplitCols($m[1], [$headPos[0], $headPos[1]], false);

                $allRows = preg_split("#((?:^|\n).+?[ ]{5,})#", $table[1], -1, PREG_SPLIT_DELIM_CAPTURE);
                $rows = [];

                if (count($allRows) > 1) {
                    array_shift($allRows);

                    for ($i = 0; $i < count($allRows) - 1; $i += 2) {
                        $rows[] = $allRows[$i] . $allRows[$i + 1];
                    }
                }

                $currency = null;
                $fees = [];

                foreach ($rows as $row) {
                    $pos = [0, $headPos[2] - $headPos[1]];
                    $cols = $this->SplitCols($row, $pos);
                    $cols[0] = preg_replace("#\s+#", ' ', trim($cols[0]));
                    $cols[1] = preg_replace("#\s+#", ' ', trim($cols[1]));

                    switch ($cols[0]) {
                        case false:
                            if (preg_match("#^\s*([A-Z]{3})\s*$#", $cols[1], $m) === 1) {
                                $currency = trim($cols[1]);
                            }

                            break;

                        case $cols[0] == 'Ticket fare:' && !empty($currency):
                            $cost = PriceHelper::cost($cols[1]);

                            break;

                        case !empty($currency):
                            $fees[] = ['name' => $cols[0], 'amount' => PriceHelper::cost($cols[1])];

                            break;
                    }
                }

                if (isset($cost)) {
                    $it['BaseFare'] = $cost;
                }

                foreach ($fees as $fee) {
                    $it['Fees'][] = ["Name" => $fee['name'], "Charge" => $fee['amount']];
                }
            }
        }
        // Grand Total:    30,000 miles + SGD 377.90
        if (preg_match('/\bGrand Total[ ]*:[ ]*(?:(?<miles>\d[,\d]*[ ]+miles)[ ]*[+][ ]*)?(?<currency>\D+)[ ]*(?<charge>\d[,.\d ]*)$/mi', $payment, $matches)) {
            if (!empty($matches['miles'])) {
                $it['SpentAwards'] = $matches['miles'];
            }
            $it['Currency'] = trim($matches['currency']);
            $it['TotalCharge'] = $this->normalizePrice($matches['charge']);
        }

        return $it;
    }

    protected function recordLocatorInArray($recordLocator, $array)
    {
        foreach ($array as $key => $value) {
            if ($value['Kind'] === 'T') {
                if ($value['RecordLocator'] === $recordLocator) {
                    return $key;
                }
            }
        }

        return false;
    }

    protected function uniqueTripSegments($it)
    {
        if ($it['Kind'] !== 'T') {
            return $it;
        }
        $uniqueSegments = [];

        foreach ($it['TripSegments'] as $segment) {
            foreach ($uniqueSegments as $key => $uniqueSegment) {
                $condition1 = $segment['FlightNumber'] !== FLIGHT_NUMBER_UNKNOWN && $uniqueSegment['FlightNumber'] !== FLIGHT_NUMBER_UNKNOWN && $segment['FlightNumber'] === $uniqueSegment['FlightNumber'];
                $condition2 = $segment['DepCode'] !== TRIP_CODE_UNKNOWN && $uniqueSegment['DepCode'] !== TRIP_CODE_UNKNOWN && $segment['DepCode'] === $uniqueSegment['DepCode']
                    && $segment['ArrCode'] !== TRIP_CODE_UNKNOWN && $uniqueSegment['ArrCode'] !== TRIP_CODE_UNKNOWN && $segment['ArrCode'] === $uniqueSegment['ArrCode'];
                $condition3 = $segment['DepDate'] !== MISSING_DATE && $uniqueSegment['DepDate'] !== MISSING_DATE && $segment['DepDate'] === $uniqueSegment['DepDate'];

                if (($condition1 || $condition2) && $condition3) {
                    if (!empty($segment['Seats'][0])) {
                        if (!empty($uniqueSegments[$key]['Seats'][0])) {
                            $uniqueSegments[$key]['Seats'] = array_merge($uniqueSegments[$key]['Seats'], $segment['Seats']);
                            $uniqueSegments[$key]['Seats'] = array_unique($uniqueSegments[$key]['Seats']);
                        } else {
                            $uniqueSegments[$key]['Seats'] = $segment['Seats'];
                        }
                    }

                    continue 2;
                }
            }
            $uniqueSegments[] = $segment;
        }
        $it['TripSegments'] = $uniqueSegments;

        return $it;
    }

    protected function splitText($textSource = '', $pattern = '', $saveDelimiter = false)
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, null, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }

    protected function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function assignLangPdf($text = ''): bool
    {
        foreach ($this->langDetectorsPdf as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function TableHeadPos($text)
    {
        $row = explode("\n", $text)[0];
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false, $trim = true)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                $v = mb_substr($row, $p, null, 'UTF-8');

                if ($trim === true) {
                    $v = trim($v);
                }
                $cols[$k][] = $v;
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
        $pos = [];
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }
}
