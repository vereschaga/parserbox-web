<?php

namespace AwardWallet\Engine\yatra\Email;

class FlBookingConfirmationPDF extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "yatra.com";
    public $reBody = [
        'en' => ['Yatra.com', 'Flight Booking Confirmation'],
    ];
    public $reSubject = [
        "#.+?\s+to\s+.+#",
    ];
    public $lang = '';
    /** @var \HttpBrowser */
    public $pdf;
    public static $dict = [
        'en' => [
        ],
    ];
    private $total = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $pdfs = $parser->searchAttachmentByName(".*pdf");

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;

            foreach ($pdfs as $pdf) {
                if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                    $NBSP = chr(194) . chr(160);
                    $html = str_replace($NBSP, ' ', html_entity_decode($html));
                    $html = str_replace("", ' ', $html);
                    $this->pdf->SetEmailBody($html);
                    $body = $this->pdf->Response['body'];

                    if ($this->assignLang($body)) {
                        $its = array_merge($its, $this->parseEmail());
                    }
                }
            }
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "FlBookingConfirmationPdf" . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $text = text(\PDF::convertToHtml($parser->getAttachmentBody($pdf)));

            if ($this->assignLang($text)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], "yatra") !== false) {
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

    private function parseEmail()
    {
        $its = [];
        $xpath = "//text()[contains(normalize-space(.),'PNR:')]/ancestor::p[1]/preceding::p[contains(.,'Hrs')][1]/preceding::p[1]";
        $tripNum = array_unique($this->pdf->FindNodes("//text()[contains(normalize-space(.),'Reference Number')]", null, "#\-\s*([A-Z\d]+)$#"));

        if (empty($tripNum)) {
            $tripNum = array_unique($this->pdf->FindNodes("//text()[contains(normalize-space(.),'Reference Number')]/following::text()[normalize-space(.)][1]", null, "#[A-Z\d]+#"));
        }

        if (count($tripNum) === 1) {
            $tripNum = array_shift($tripNum);
        } else {
            $tripNum = null;
        }
        $pax = array_values(array_unique($this->pdf->FindNodes("//text()[contains(.,'Adult')]/ancestor::p[1]//text()[not(contains(.,'Adult'))]")));
        $status = $this->pdf->FindSingleNode("//text()[contains(normalize-space(.),'Your ﬂight booking is')]", null, false, "/Your ﬂight booking is\s+(.+?)\.?$/");

        if (empty($status)) {
            $status = $this->pdf->FindSingleNode("//text()[contains(normalize-space(.),'Your ﬂight booking is')]/following::text()[normalize-space(.)!=''][1]");
        }
        $nodes = $this->pdf->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            $rl = $this->pdf->FindSingleNode("./following::p[contains(normalize-space(.),'PNR:')][1]", $root, true, "#PNR:\s+([A-Z\d]+)#");

            if (!empty($rl)) {
                $airs[$rl][] = $root;
            } else {
                $airs[$tripNum][] = $root;
            }
        }

        foreach ($airs as $rl => $roots) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $rl;
            $it['TripNumber'] = $tripNum;
            $it['Status'] = $status;
            $it['Passengers'] = $pax;

            foreach ($roots as $root) {
                $seg = [];

                $seg['ArrName'] = $this->pdf->FindSingleNode(".", $root);
                $node = implode(" ", $this->pdf->FindNodes("./following::p[1]//text()", $root));

                if (preg_match("#(.+?)\s*(Airport:.+)#", $node, $m)) {
                    $seg['ArrName'] .= ' ' . $m[2];
                    $seg['ArrDate'] = strtotime($this->normalizeDate($m[1]));
                }
                $node = implode(' ',
                    $this->pdf->FindNodes("./following::p[contains(.,'-')][1]/preceding::p[1]/following::p[position()<=2]",
                        $root));

                if (preg_match("#([A-Z\d]{2})\s*\-\s*(\d+)#", $node, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }
                $node = implode("\n", $this->pdf->FindNodes("./following::p[contains(.,'-')][1]/preceding::p[1]/following::p[normalize-space(.)][position()<=4][not(contains(.,'PNR'))]", $root));

                if (preg_match("#[A-Z\d]{2}\s*\-\s*\d+\s+(?:([^\|]+)\n)?\s*(.+?)\s*\|\s*(.+?)\s*\|\s*(.+)#", $node,
                    $m)) {
                    if (isset($m[1])) {
                        $seg['Aircraft'] = $m[1];
                    }
                    $seg['Cabin'] = $m[2];
                    //					$seg['Stops'] = $m[3];// not always
                }
                $seg['DepName'] = $this->pdf->FindSingleNode("./preceding::p[1]/following::p[contains(.,'PNR')][1]/following::p[contains(.,'Hrs')][1]/preceding::p[1]", $root);
                $node = implode(" ", $this->pdf->FindNodes("./preceding::p[1]/following::p[contains(.,'PNR')][1]/following::p[contains(.,'Hrs')][1]//text()", $root));

                if (preg_match("#(.+?)\s*(Airport:.+)#", $node, $m)) {
                    $seg['DepName'] .= ' ' . $m[2];
                    $seg['DepDate'] = strtotime($this->normalizeDate($m[1]));
                }
                $seg['Duration'] = trim(implode(" ", $this->pdf->FindNodes("./preceding::p[1]/following::p[contains(.,'PNR')][1]/following::p[contains(.,'Hrs')][1]/following::p[position()<3]", $root)));

                if (!empty($seg['DepDate']) && !empty($seg['ArrDate']) && !empty($seg['FlightNumber'])) {
                    $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                }

                $it['TripSegments'][] = $seg;
            }
            $its[] = $it;
        }
        $tot = $this->getTotalCurrency(str_replace("Rs.", "INR", implode(' ', $this->pdf->FindNodes("//text()[contains(normalize-space(.),'Total Amount')]/ancestor::p[1]/following::p[normalize-space(.)!=''][position()<=2]"))));

        if (!empty($tot['Total'])) {
            if (count($its) === 1) {
                $its[0]['TotalCharge'] = $tot['Total'];
                $its[0]['Currency'] = $tot['Currency'];
                $this->total[] = ['Amount' => $tot['Total'], 'Currency' => $tot['Currency']]; // collect for amount
                $tot = $this->getTotalCurrency(str_replace("Rs.", "INR", implode(' ', $this->pdf->FindNodes("//text()[contains(normalize-space(.),'Flight Price')]/ancestor::p[1]/following::p[normalize-space(.)!=''][position()<=2]"))));

                if (!empty($tot['Total'])) {
                    $its[0]['BaseFare'] = $tot['Total'];
                }
            }
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#(\d+:\d+)\s*Hrs,\s+(\d+)\w*\s+(\w+).?(\d{2})#',
        ];
        $out = [
            '$2 $3 20$4 $1',
        ];
        $str = preg_replace($in, $out, $date);

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
}
