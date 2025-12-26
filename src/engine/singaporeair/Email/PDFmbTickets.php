<?php

namespace AwardWallet\Engine\singaporeair\Email;

class PDFmbTickets extends \TAccountChecker
{
    public $mailFiles = "singaporeair/it-6748015.eml, singaporeair/it-8005605.eml, singaporeair/it-8169187.eml, singaporeair/it-8170357.eml, singaporeair/it-8402020.eml"; // +1 bcdtravel(pdf)[en]

    public $reFrom = 'singaporeair.com.sg';
    public $reBodyPDF = [
        'en'  => ['Thank you for using the Singapore Airlines Electronic Ticket service', 'This is your travel itinerary'],
        'en2' => ['Thank you for booking your flight(s) with SilkAir', 'This is your travel itinerary'],
    ];
    public $reSubject = [
        'notDeterminate',
    ];
    public $lang = '';
    public $pdf;
    public static $dict = [
        'en' => [],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[normalize-space(@alt)='Singapore Airlines']")->length > 0
                && $this->http->XPath->query("(//table[thead[" . $this->containsAllLang('Departs') . " and " . $this->containsAllLang('Arrives') . "]]//tr[td[@colspan=4]])")->length > 0) {
            return null;
        } //got to parse by BookingConfirmation3.php

        $its = [];
        $pdfs = $parser->searchAttachmentByName(".*pdf");

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;

            foreach ($pdfs as $pdf) {
                if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                    $NBSP = chr(194) . chr(160);
                    $this->pdf->SetBody(str_replace($NBSP, ' ', html_entity_decode($html)));

                    if ($this->AssignLang($html)) {
                        $its[] = $this->parseEmail();
                    }
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }
        $its = $this->mergeItineraries($its);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[normalize-space(@alt)='Singapore Airlines']")->length > 0
                && $this->http->XPath->query("(//table[thead[" . $this->containsAllLang('Departs') . " and " . $this->containsAllLang('Arrives') . "]]//tr[td[@colspan=4]])")->length > 0) {
            return false;
        } //got to parse by BookingConfirmation3

        $pdf = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            return $this->AssignLang($text);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers['subject'], $reSubject) !== false) {
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

    protected function mergeItineraries($its)
    {
        $its2 = $its;

        foreach ($its2 as $i => $it) {
            if ($i && ($j = $this->findRL($i, $it['RecordLocator'], $its)) != -1) {
                $its[$j]['TripSegments'] = array_merge($its[$j]['TripSegments'], $its[$i]['TripSegments']);
                $its[$j]['TripSegments'] = array_map('unserialize', array_unique(array_map('serialize', $its[$j]['TripSegments'])));
                $its[$j]['Passengers'] = array_merge($its[$j]['Passengers'], $its[$i]['Passengers']);
                $its[$j]['Passengers'] = array_map("unserialize", array_unique(array_map("serialize", $its[$j]['Passengers'])));
                $its[$j]['TicketNumbers'] = array_merge($its[$j]['TicketNumbers'], $its[$i]['TicketNumbers']);
                $its[$j]['TicketNumbers'] = array_map("unserialize", array_unique(array_map("serialize", $its[$j]['TicketNumbers'])));
                unset($its[$i]);

                if (isset($its[$j]['BaseFare'])) {
                    unset($its[$j]['BaseFare']);
                }

                if (isset($its[$j]['TotalCharge'])) {
                    unset($its[$j]['TotalCharge']);
                }

                if (isset($its[$j]['Currency'])) {
                    unset($its[$j]['Currency']);
                }
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

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $namePrefixes = [ // Taken from: https://www.singaporeair.com/ru_RU/ppsclub-krisflyer/registration-form/
            'Mr',
            'Mrs',
            'Miss',
            'Mdm',
            'Ms',
            'Mstr',
            'Dr',
            'Prof',
            'Assoc Prof',
            'Capt',
            'Count',
            'Countess',
            'Datin',
            'Datin Seri',
            'Datin Sri',
            'Datin Wira',
            'Dato',
            'Dato Seri',
            'Dato Sri',
            'Dato Wira',
            'Datuk',
            'Datuk Seri',
            'Datuk Sri',
            'Datuk Wira',
            'Dtn Paduka',
            'Duchess',
            'Duke',
            'Earl',
            'Engku',
            'Father',
            'HE',
            'HH',
            'Hon',
            'HRH',
            'King',
            'Lady',
            'Lord',
            'Lt Gen',
            'Major',
            'President',
            'Prince',
            'Princess',
            'Prof Dr',
            'Puan Sri',
            'Queen',
            'Rev',
            'Senator',
            'Sir',
            'Sultan',
            'Tan Sri',
            'Tan Sri Dato',
            'Tan Sri Dtk',
            'Tengku',
            'Toh Puan',
            'Tun',
            'Tunku',
            'Venerable',
        ];
        $xpathFragment1 = '(starts-with(normalize-space(.),"' . implode(' ") or starts-with(normalize-space(.),"', $namePrefixes) . '"))';
        $it['Passengers'] = array_values(array_unique($this->pdf->FindNodes('//text()[contains(.,"Booking reference")]/preceding::text()[normalize-space(.)][' . $xpathFragment1 . ' and position()<10][1]')));
        $xpathFragment2 = "(//text()[contains(.,'{$this->t('Booking reference')}')])[1]";
        $it['RecordLocator'] = $this->pdf->FindSingleNode($xpathFragment2, null, true, "#.+?:\s*([A-Z\d]+)#");

        if (empty($it['RecordLocator'])) {
            $bookingReferenceTexts = $this->pdf->FindNodes($xpathFragment2 . "/following::text()[string-length(normalize-space(.))>4][position()<5]");

            foreach ($bookingReferenceTexts as $bookingReferenceText) {
                if (preg_match('/([A-Z\d]{5,})$/', $bookingReferenceText, $matches)) {
                    $it['RecordLocator'] = $matches[1];

                    break;
                }
            }
        }
        $it['TicketNumbers'] = array_filter($this->pdf->FindNodes("//text()[contains(.,'Electronic ticket:')]/following::text()[string-length(normalize-space(.))>3][1]", null, "#^\s*([A-Z\d\-]+)\s*$#"));
        $it['AccountNumbers'] = array_filter($this->pdf->FindNodes("//text()[contains(.,'KrisFlyer')]/following::text()[string-length(normalize-space(.))>3][1]", null, "#^\s*([A-Z\d\-]+)\s*$#"));
        $it['ReservationDate'] = strtotime($this->pdf->FindSingleNode("(//text()[contains(.,'Date of issue:')]/following::text()[string-length(normalize-space(.))>5][1])[1]"));

        if (count($it['Passengers']) === 1) { // otherwise Sum of Charges will be uncorrect
            $tot = $this->getTotalCurrency(implode(' ', $this->pdf->FindNodes("//text()[contains(.,'Ticket fare')]/following::text()[string-length(normalize-space(.))>2][position()<3]")));

            if (!empty($tot['Total'])) {
                $it['BaseFare'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }
            $tot = $this->getTotalCurrency(implode(' ', $this->pdf->FindNodes("//text()[contains(.,'Ticket amount')]/following::text()[string-length(normalize-space(.))>2][position()<3]")));

            if (!empty($tot['Total'])) {
                $it['TotalCharge'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }
        }
        $text = implode("\n", $this->pdf->FindNodes("//text()[contains(.,'Flight Details')]/following::text()[string-length(normalize-space(.))>3]"));
        $text = "prefixForSplitterToCut\n" . strstr($text, 'Payment details', true);
        $values = $this->splitter("#^\s*([A-Z\d]{2}\s*\d+)\s*$#m", $text);

        foreach ($values as $node) {
            $seg = [];

            if (preg_match("#\s*([A-Z\d]{2})\s*(\d+)\n.+\n(.+)#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
                $seg['Cabin'] = $m[3];
            }

            if (preg_match("#Departs:\n(.+)\s+\(([A-Z]{3})\)(?:\s*(.*(?:Terminal|T).*))?\n(.+\d+:\d+)#i", $node, $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepCode'] = $m[2];

                if (isset($m[3]) && !empty($m[3])) {
                    $seg['DepartureTerminal'] = $m[3];
                }
                $seg['DepDate'] = strtotime($m[4]);
            }

            if (preg_match("#Arrives:\n(.+)\s+\(([A-Z]{3})\)(?:\s*(.*(?:Terminal|T).*))?\n(.+\d+:\d+)#i", $node, $m)) {
                $seg['ArrName'] = $m[1];
                $seg['ArrCode'] = $m[2];

                if (isset($m[3]) && !empty($m[3])) {
                    $seg['ArrivalTerminal'] = $m[3];
                }
                $seg['ArrDate'] = strtotime($m[4]);
            }
            $it['TripSegments'][] = $seg;
        }

        return $it;
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
        if (isset($this->reBodyPDF)) {
            foreach ($this->reBodyPDF as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

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

    private function containsAllLang($field)
    {
        $fields = [];
        $fields[] = $field;

        foreach (self::$dict as $lang => $values) {
            if (isset($values[$field])) {
                $fields[] = $values[$field];
            }
        }

        if (count($fields) == 0) {
            return 'false';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $fields)) . ')';
    }
}
