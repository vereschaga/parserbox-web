<?php

namespace AwardWallet\Engine\spicejet\Email;

class It3664262 extends \TAccountCheckerExtended
{
    public $mailFiles = "spicejet/it-10834475.eml, spicejet/it-10834814.eml, spicejet/it-10835345.eml, spicejet/it-10835573.eml, spicejet/it-2002156.eml, spicejet/it-2283947.eml, spicejet/it-2676615.eml, spicejet/it-2676616.eml, spicejet/it-4639303.eml, spicejet/it-4795048.eml, spicejet/it-4856693.eml";

    public $reFrom = 'spicejet.com';
    public $reSubject = [
        'Confirmation Number (PNR)',
        'SpiceJet Booking PNR',
    ];

    public $langDetectors = [
        'en' => ['is attached', 'FLIGHT NO.', 'Flight No.', 'Dep.Time', 'Passenger(s) Information', 'FROM / TERMINAL', 'FROM/TERMINAL', 'From / Terminal', 'From/Terminal'],
    ];

    public static $dictionary = [
        'en' => [
            'S.NO'           => ['S.NO', 'S.No'],
            'PASSENGER NAME' => ['PASSENGER NAME', 'Passenger Name'],
        ],
    ];

    public $lang = '';

    /** @var \HttpBrowser */
    private $pdf;

    public function parseEmail(&$itineraries)
    {
        $patterns = [
            'travellerName' => '[A-z][-.\'A-z ]*[A-z]', // Mr. Hao-Li Huang
        ];

        $text = text($this->http->Response['body']);

        if (isset($this->pdf)) {
            $pdf = text($this->pdf->Response['body']);
        }

        $it = [];
        $it['Kind'] = 'T';

        // RecordLocator
        $it['RecordLocator'] = re("#Your\s+confirmation\s+number\s+\(PNR\)\s+is\s+(\w+)#", $text);

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = re("#Confirmation\s+Number\s+\(PNR\)\s*:\s*(\w+)#", $text);
        }

        if (empty(re("#confirmation\s+number#i", $text))) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }

        // Passengers
        $passengers = $this->http->FindNodes("//*[normalize-space(.)='Name']/ancestor::tr[1]/following-sibling::tr/td[1]|//text()[normalize-space(.)='PASSENGERS(S)']/ancestor::tr[1]/following-sibling::tr");

        if (empty($passengers)) {
            $passengers = $this->http->FindNodes("//img[contains(@src, 'itinerary-passengernfo-header')]/ancestor::table[1]/following::table[1]//td//div[starts-with(translate(normalize-space(), '123456789', '000000000'), '0')]", null, "#\d+\.\s*(.+)\(#");
            $passengers = array_values(array_filter(array_map('trim', $passengers)));
        }

        if (empty($passengers[0])) {
            $passengers = $this->http->FindNodes("//tr[contains(normalize-space(.), 'Passenger(s) Information') and not(.//tr)]/following-sibling::tr/td[1]", null, "#\d+\.\s*(.+)#");
            $passengers = array_values(array_filter(array_map('trim', $passengers)));
        }

        if (empty($passengers[0])) { // it-10834814.eml
            $passengers = $this->http->FindNodes("//tr[ ./*[1][{$this->contains($this->t('S.NO'))}] and ./*[2][{$this->contains($this->t('PASSENGER NAME'))}] ]/following-sibling::*[ ./*[3] ]/*[2]", null, "/^\s*({$patterns['travellerName']})\s*$/");
            $passengers = array_values(array_filter(array_map('trim', $passengers)));
        }

        if (!empty($passengers[0])) {
            $it['Passengers'] = $passengers;
        }

        // TotalCharge
        $it['TotalCharge'] = cost(re("#Total\s+Price\s+-\s*\d.+#", $text));

        if (empty($it['TotalCharge'])) {
            $it['TotalCharge'] = cost(re('/Amount to pay\s+([\d\.]+)/', $text));
        }

        if (empty($it['TotalCharge']) && isset($pdf)) {
            $it['TotalCharge'] = cost(re("#Total\s+Price\s+-\s+.+#", $pdf));
        }

        // Currency
        $it['Currency'] = currency(re("#Total\s+Price\s+-\s+\d.+#", $text));

        if (empty($it['Currency']) && isset($pdf)) {
            $it['Currency'] = currency(re("#Total\s+Price\s+-\s+.+#", $pdf));
        }

        if (empty($it['Currency']) && false !== strpos($text, 'change fee of INR')) {
            $it['Currency'] = 'INR';
        }

        // Status
        $status = $this->http->FindSingleNode("/descendant::text()[starts-with(normalize-space(.),'Status:')][1]", null, true, '/^[^:]+:\s*(.+)/');

        if (empty($status)) {
            $status = $this->http->FindSingleNode("/descendant::text()[starts-with(normalize-space(.),'Status:')][1]/following::text()[normalize-space(.)][1]");
        }

        if (!empty($status)) {
            $it['Status'] = $status;
        }

        // Cancelled
        if (!empty($it['Status']) && strcasecmp($it['Status'], 'Cancelled') === 0) {
            $it['Cancelled'] = true;
        }

        $xpath = "//*[normalize-space(.)='Flight No.' or normalize-space(.)='FLIGHT NO.']/ancestor::tr[1]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->debug("segments root not found: $xpath");
        }

        //		$year = $this->http->FindSingleNode("//*[contains(text(),'BOOKING') and contains(text(),'DETAILS')]/ancestor::table[1]/following-sibling::table[3]//td[2]", null, true, "#(\d+)$#");
        $flns = [];

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[1]", $root, true, "#\w+,?\s+(\d+\s+\w+,?\s+\d{2,4})#")));

            $itsegment = [];

            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[2]", $root, true, "/(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(\d+)/");

            if (isset($flns[$itsegment['FlightNumber']])) {
                continue;
            }
            $flns[$itsegment['FlightNumber']] = 1;

            // DepCode
            // ArrCode
            $itsegment['ArrCode'] = $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = trim($this->http->FindSingleNode("./td[3]", $root, true, "#([^/]+)#"));

            // DepartureTerminal
            $itsegment['DepartureTerminal'] = trim($this->http->FindSingleNode("./td[3]", $root, true, "#/(.+)#"));

            // DepDate
            $time = str_replace('/', '.', $this->http->FindSingleNode("./td[5]", $root));

            if (stripos($time, "PM") && preg_match("#(\d+):(\d+)\s*PM#i", $time, $m)) {
                if ($m[1] > 12) {
                    $time = $m[1] . ':' . $m[2];
                }
            }
            $itsegment['DepDate'] = strtotime($time, $date);

            // ArrName
            $itsegment['ArrName'] = trim($this->http->FindSingleNode("./td[4]", $root, true, "#([^/]+)#"));

            // ArrivalTerminal
            $itsegment['ArrivalTerminal'] = trim($this->http->FindSingleNode("./td[4]", $root, true, "#/(.+)#"));

            // ArrDate
            $time = str_replace('/', '.', $this->http->FindSingleNode("./td[6]", $root));

            if (stripos($time, "PM") && preg_match("#(\d+):(\d+)\s*PM#i", $time, $m)) {
                if ($m[1] > 12) {
                    $time = $m[1] . ':' . $m[2];
                }
            }
            $itsegment['ArrDate'] = strtotime($time, $date);

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[2]", $root, true, "/([A-Z][A-Z\d]|[A-Z\d][A-Z])\s+\d+/");

            if (isset($itsegment['FlightNumber']) && isset($itsegment['AirlineName'])) {
                $route = $this->http->FindSingleNode("(//img[contains(@src, 'itinerary-add-ons-header') or contains(@src, 'passengernfo')]/ancestor::table[1]/following::table[1]//text()[contains(.,'" . $itsegment['AirlineName'] . " " . $itsegment['FlightNumber'] . "')])[1]", null, true, "#" . $itsegment['AirlineName'] . " " . $itsegment['FlightNumber'] . "\s*\(\s*[A-Z]{3}\s*-\s*[A-Z]{3}\s*\)#");

                if (!empty($route) && preg_match("#([A-Z]{3})\s*-\s*([A-Z]{3})#", $route, $m)) {
                    $itsegment['DepCode'] = $m[1];
                    $itsegment['ArrCode'] = $m[2];
                }
            }

            // Seats
            if (isset($itsegment['FlightNumber']) && isset($itsegment['AirlineName']) && !empty($this->http->FindSingleNode("(//img[contains(@src, 'itinerary-add-ons-header') or contains(@src, 'passengernfo')]/ancestor::table[1]/following::table[1]//span[contains(.//img/@src, 'seat') or contains(.//img/@src, 'spicemax-icon')])[1]"))) {
                $itsegment['Seats'] = [];
                $xpath = "//img[contains(@src, 'itinerary-add-ons-header') or contains(@src, 'passengernfo')]/ancestor::table[1]/following::table[1]//text()[contains(.,'" . $itsegment['AirlineName'] . " " . $itsegment['FlightNumber'] . "')]/ancestor::td[1]/following-sibling::td[2]//span[contains(.//img/@src, 'seat') or contains(.//img/@src, 'spicemax-icon')][normalize-space()]";
                $seats = $this->http->XPath->query($xpath);

                foreach ($seats as $sroot) {
                    $s = $this->http->FindSingleNode(".", $sroot);

                    if (strpos($s, ',') === false && preg_match("#^\s*(\d{1,3}[A-Z])\s*$#", $s)) {
                        $itsegment['Seats'][] = $s;
                    } else {
                        $flight = $this->http->FindSingleNode("./ancestor::td[1]/preceding-sibling::td[2]", $sroot);
                        $fpos = stripos($flight, $itsegment['AirlineName'] . " " . $itsegment['FlightNumber']);
                        $fOrder = $fpos == 0 ? 0 : substr_count($flight, ',', 0, $fpos);
                        $sa = explode(",", $s);

                        if (count($sa) == count(explode(',', $flight)) && !empty($sa[$fOrder]) && preg_match("#^\s*(\d{1,3}[A-Z])\s*$#", $sa[$fOrder])) {
                            $itsegment['Seats'][] = $sa[$fOrder];
                        }
                    }
                }
            }

            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(.,"www.spicejet.com") or contains(.,"@spicejet.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.spicejet.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = false;

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }

        if ($html = re("#<body>\s+(<table.*?</table>)\s+</body>#msi", $this->http->Response["body"])) {
            $this->http->SetEmailBody($html);
        }

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                $this->pdf = clone $this->http;
                $this->pdf->SetEmailBody($html);
            }
        }

        $this->assignLang();

        $itineraries = [];
        $this->parseEmail($itineraries);

        $result = [
            'emailType'  => 'It3664262' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        //		$this->logger->alert($str);
        $in = [
            "#^\s*(\d+)\s+(\w+),?\s+(\d{2,4})\s*$#", // THU 26 OCT, 2017, THU, 26 OCT, 2017
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        //		if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
        return $str;
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }
}
