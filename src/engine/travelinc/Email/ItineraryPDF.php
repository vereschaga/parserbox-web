<?php

namespace AwardWallet\Engine\travelinc\Email;

// parsers with similar formats: bcd/TravelReceiptPdf

class ItineraryPDF extends \TAccountChecker
{
    public $mailFiles = "travelinc/it-6330001.eml"; // +1 bcdtravel(pdf)[en]

    public $reFrom = "worldtravelinc.com";
    public $reBody = [
        'en' => ['World Travel Corporate Headquarters', 'World Travel Record Locator'],
    ];
    public $reSubject = [
        '#TICKETED\s+INVOICE\s+for\s+.+?\s+departing\s+.+?\d+$#i',
    ];
    public $lang = '';

    public $hotelsCount = 0;
    public $paymentFlights;
    public $pdfNamePattern = 'World\s*Travel\s*Itinerary.*pdf';
    public static $dict = [
        'en' => [
            'itinerariesEnd' => ['Ticket Detail', 'Estimated Trip Total'],
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            $html = '';

            foreach ($pdfs as $pdf) {
                if (($html .= \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }
        $text = text($html);

        $this->assignLang($text);

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return null;
        }

        $its = $this->parseEmail($text);

        $result = [
            'emailType' => 'ItineraryPDF' . ucfirst($this->lang),
        ];

        if (!empty($this->paymentFlights['Currency'])) {
            if (count($its) === 1) {
                $its[0]['Currency'] = $this->paymentFlights['Currency'];
                $its[0]['TotalCharge'] = $this->paymentFlights['Total'];
            } else {
                $result['parsedData']['TotalCharge']['Currency'] = $this->paymentFlights['Currency'];
                $result['parsedData']['TotalCharge']['Amount'] = $this->paymentFlights['Total'];
            }
        }

        $result['parsedData']['Itineraries'] = $its;

        return $result;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]));

            if (!$text) {
                return false;
            }

            return $this->assignLang($text);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
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

    public function findСutSection($input, $searchStart, $searchFinish = null)
    {
        $inputResult = null;

        if ($searchStart !== 0) {
            $left = mb_strstr($input, $searchStart);
        } else {
            $left = $input;
        }

        if ($searchFinish === null) {
            return $left;
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

    protected function mergeItineraries($its)
    {
        $its2 = $its;

        foreach ($its2 as $i => $it) {
            if ($it['Kind'] !== 'T') {
                continue;
            }

            if ($i && ($j = $this->findRL($i, $it['RecordLocator'], $its)) !== -1) {
                $its[$j]['TripSegments'] = array_merge($its[$j]['TripSegments'], $its[$i]['TripSegments']);
                $its[$j]['TripSegments'] = array_map('unserialize', array_unique(array_map('serialize', $its[$j]['TripSegments'])));
                $its[$j]['Passengers'] = array_merge($its[$j]['Passengers'], $its[$i]['Passengers']);
                $its[$j]['Passengers'] = array_map("unserialize", array_unique(array_map("serialize", $its[$j]['Passengers'])));
                $its[$j]['TicketNumbers'] = array_merge($its[$j]['TicketNumbers'], $its[$i]['TicketNumbers']);
                $its[$j]['TicketNumbers'] = array_map("unserialize", array_unique(array_map("serialize", $its[$j]['TicketNumbers'])));
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

    private function parseEmail($plainText)
    {
        $its = [];

        $text = $this->findСutSection($plainText, $this->t('Travel Information'), $this->t('Contact Us'));
        $itinerariesText = $this->findСutSection($text, 0, $this->t('itinerariesEnd'));

        // Fourou/Theodora Marina VOLU    |    Delatorre/Paulo 800240
        $traveller = $this->re("#^\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]][ ]*\/[ ]*[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[ ]+(?:VOLU|\d{6})[ ]*(?:\n|$)#", $itinerariesText);
        $tripNum = $this->re("#World Travel Record Locator:\s+([A-Z\d]+)#", $itinerariesText);

        $ticketDetail = $this->findСutSection($text, $this->t('Ticket Detail'), null);
        $ticketNumber = $this->re("#Ticket Number:\s*(.*)\s+Issued#", $ticketDetail);
        $dateRes = $this->re("#Ticket Number:\s*.*\s+Issued:\s+(.+)\n#", $ticketDetail);
        $this->paymentFlights = $this->getTotalCurrency($this->re('#Total Charges[\s\*:]+(.+?)\s+Invoice Number#', $ticketDetail));

        $tripTotal = $this->findСutSection($text, $this->t('Estimated Trip Total'), null);

        $roots = $this->splitter("/^([ ]*(?:AIR|HOTEL)(?: - |[ ]{2}|$))/m", $itinerariesText);

        foreach ($roots as $root) {
            if (preg_match("/^[ ]*AIR\b/", $root)) {
                $itFlight = $this->parseFlight($root);

                if ($traveller) {
                    $itFlight['Passengers'] = [$traveller];
                }

                if ($tripNum) {
                    $itFlight['TripNumber'] = $tripNum;
                }

                if ($ticketNumber) {
                    $itFlight['TicketNumbers'] = [$ticketNumber];
                }

                if ($dateRes) {
                    $itFlight['ReservationDate'] = strtotime($this->normalizeDate($dateRes));
                }
                $its[] = $itFlight;
            }

            if (preg_match("/^[ ]*HOTEL\b/", $root)) {
                $itHotel = $this->parseHotel($root);

                if ($traveller) {
                    $itHotel['GuestNames'] = [$traveller];
                }

                if ($tripNum) {
                    $itHotel['TripNumber'] = $tripNum;
                }
                $its[] = $itHotel;
                ++$this->hotelsCount;
            }
        }

        if ($this->hotelsCount === 1) {
            $paymentHotel = $this->getTotalCurrency($this->re('/^[ ]*Hotel:\s+([A-Z]{3}[ ]*\d[,.\'\d ]*)$/m', $tripTotal));

            foreach ($its as &$value) {
                if ($value['Kind'] === 'R') {
                    $value['Currency'] = $paymentHotel['Currency'];
                    $value['Total'] = $paymentHotel['Total'];

                    break;
                }
            }
        }

        $its = $this->mergeItineraries($its);

        return $its;
    }

    private function parseFlight($root)
    {
        $it = [];
        $it = ['Kind' => 'T', 'TripSegments' => []];

//        $date = $this->re("#^\s*AIR\s+(.+)#", $root);

        $it['RecordLocator'] = $this->re("#Airline Booking Reference:\s+([A-Z\d]+)#", $root);

        $seg = [];
        $seg['AirlineName'] = $this->re('#Flight\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+#', $root);
        $seg['FlightNumber'] = $this->re('#Flight\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)#', $root);
        $seg['Cabin'] = $this->re('#Flight\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+\s+(.+)\s+Class#', $root);

        $seg['DepCode'] = $this->re('#Depart:\s+([A-Z]{3})#', $root);
        $departure = $this->re('#Depart:\s+[A-Z]{3}\s+(.+)#', $root);

        if (preg_match("#(.+?),\s+Terminal\s*(.*)#i", $departure, $m)) {
            $seg['DepName'] = $m[1];
            $seg['DepartureTerminal'] = $m[2];
        } else {
            $seg['DepName'] = $departure;
        }
        $seg['DepDate'] = strtotime($this->normalizeDate($this->re('#Depart:\s+.+?\s+(\d+:\d+.+?)\n#s', $root)));

        $seg['ArrCode'] = $this->re('#Arrive:\s+([A-Z]{3})#', $root);
        $arrival = $this->re('#Arrive:\s+[A-Z]{3}\s+(.+)#', $root);

        if (preg_match("#(.+?),\s+Terminal\s*(.*)#i", $arrival, $m)) {
            $seg['ArrName'] = $m[1];
            $seg['ArrivalTerminal'] = $m[2];
        } else {
            $seg['ArrName'] = $arrival;
        }
        $seg['ArrDate'] = strtotime($this->normalizeDate($this->re('#Arrive:\s+.+?\s+(\d+:\d+.+?)\n#s', $root)));

        $seg['Duration'] = $this->re('#Duration:\s+(\d+\s+.+?\s+\d+\s+minute\(s\))#', $root);
        $seg['Meal'] = $this->re('#Meal:\s+(.*)#', $root);
        $seg['Aircraft'] = $this->re('#Equipment:\s+(.*)#', $root);

        $it['Status'] = $this->re('#Status:\s+(.*)#', $root);

        $it['TripSegments'][] = $seg;

        return $it;
    }

    private function parseHotel($root)
    {
        $it = [];
        $it['Kind'] = 'R';

        $it['HotelName'] = $this->re('/^[ ]*(.{3,}?)[ ]*$\s+^[ ]*Address:/m', $root);
        $it['Address'] = preg_replace('/\s+/', ' ', $this->re('/^[ ]*Address:\s*([\s\S]{3,})[ ]*$\s+^[ ]*Tel:/m', $root));

        $it['Phone'] = $this->re('/^[ ]*Tel:\s*([+)(\d][-. \d)(]{5,}[\d)(])[ ]*$/m', $root);
        $it['Fax'] = $this->re('/^[ ]*Fax:\s*([+)(\d][-. \d)(]{5,}[\d)(])[ ]*$/m', $root);

        $datesText = $this->re('/^[ ]*Check In\/Check Out:\s*(.{6,})[ ]*$/m', $root);
        $dates = preg_split('/\s+-\s+/', $datesText);

        if (count($dates) === 2) {
            $it['CheckInDate'] = strtotime($dates[0]);
            $it['CheckOutDate'] = strtotime($dates[1]);
        }

        $it['Status'] = $this->re('/^[ ]*Status:\s+(.+?)[ ]*$/m', $root);

        $it['RoomType'] = $this->re('/^[ ]*Room Type:\s+(.+?)[ ]*$/m', $root);

        $it['Guests'] = $this->re('/^[ ]*Number of Persons:\s+(\d{1,3})[ ]*$/m', $root);

        $it['Rate'] = $this->re('/^[ ]*Rate per night:\s+(.*\d.*?)[ ]*$/m', $root);

        $it['ConfirmationNumber'] = $this->re('/^[ ]*Confirmation:\s+([A-Z\d]{5,})[ ]*$/m', $root);

        $cancellation = $this->re('/^[ ]*Cancellation Policy:\s+(.+?)[ ]*$/m', $root);

        if (!$cancellation) {
            $cancellation = $this->re('/\bCXL:[ ]*(.+?)[ ]*$/m', $root);
        }

        if ($cancellation) {
            $it['CancellationPolicy'] = $cancellation;
        }

        return $it;
    }

    private function normalizeDate($date)
    {
        $in = [
            //08/28/2015
            '#^(\d+)\/(\d+)\/(\d+)$#',
            //01:40 PM Monday, October 19, 2015
            '#^(\d+:\d+\s*(?:[ap]m)?)\s+\w+,\s+(\w+)\s+(\d+),\s+(\d+)$#i',
        ];
        $out = [
            '$3-$1-$2',
            '$3 $2 $4 $1',
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

    private function assignLang($body)
    {
        $NBSP = chr(194) . chr(160);
        $body = str_replace($NBSP, ' ', $body);

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

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)) {
            $m['t'] = preg_replace("#,(\d{3})$#", '$1', $m['t']);

            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
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
