<?php

namespace AwardWallet\Engine\hawaiian\Email;

class PDF extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "hawaiian/it-5099695.eml";
    public $reSubject = [
        'en' => ["hawaiian"],
    ];
    public $typesCount = "2";
    public $lang = 'es';
    public $pdf;
    public $date;
    public $pdfText;
    public static $dict = [
        'en' => [
            'BODY' => 'Confirmation Code',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $NBSP = chr(194) . chr(160);
        $this->date = strtotime($parser->getDate());
        $its = [];
        $pdfs = $parser->searchAttachmentByName(".*Manage\sMy\sTrip.*pdf");

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $text = '';

            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    $this->AssignLang($text);
                    $this->pdfText = str_replace($NBSP, ' ', html_entity_decode($text));
                    $html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);
                    $this->pdf->SetBody(str_replace($NBSP, ' ', html_entity_decode($html)));
                    $its[] = $this->parseEmailTrip();
                }
            }
        }
        $pdfs = $parser->searchAttachmentByName(".*Receipt.*pdf");

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $text = '';

            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    $this->AssignLang($text);
                    $this->pdfText = str_replace($NBSP, ' ', html_entity_decode($text));
                    $html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);
                    $this->pdf->SetBody(str_replace($NBSP, ' ', html_entity_decode($html)));
                    $its[] = $this->parseEmailReceipt();
                }
            }
        }

        if (count($its) == 0) {
            return null;
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "PDF",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $NBSP = chr(194) . chr(160);
        $pdf = $parser->searchAttachmentByName('.*(?:Manage\sMy\sTrip|Receipt).*pdf');

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));
            $text = str_replace($NBSP, ' ', html_entity_decode($text));

            if (stripos($text, 'hawaiianairlines.com') && stripos($text, 'HawaiianMiles')) {
                foreach (self::$dict as $lang => $reBody) {
                    if (stripos($text, $reBody['BODY']) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
        /*
                if (isset($this->reSubject)) {
                    foreach ($this->reSubject as $reSubject) {
                        if (stripos($headers["subject"], $reSubject[0]) !== false) {
                            return true;
                        }
                    }
                }
                return false;
        */
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

    protected function findСutSection($input, $searchStart, $searchFinish)
    {
        $input = mb_stristr(mb_stristr($input, $searchStart), $searchFinish, true);

        return mb_substr($input, mb_strlen($searchStart));
    }

    private function parseEmailTrip()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];

        if (preg_match("#Confirmation Code.+?\s+([A-Z\d]+)\s+Your#s", $this->pdfText, $m)) {
            $it['RecordLocator'] = $m[1];
        }

        $text = $this->findСutSection($this->pdfText, 'Passengers', 'Additional Passenger Information');
        $text = str_replace("­", " ", $text); //html entity &shy; replace

        if (preg_match_all("#(?:TICKET \#|\d)\s+([\s\w]+?)\s+(?:HA(?:\s+\d+){3}|\d)#u", $text, $m)) {
            if (is_array($m[0])) {
                $it['Passengers'] = $m[1];
            } else {
                $it['Passengers'][] = $m[1];
            }
        }

        if (preg_match_all("#(HA(?:\s+\d+){3})#", $text, $m)) {
            if (is_array($m[0])) {
                $it['AccountNumbers'] = $m[1];
            } else {
                $it['AccountNumbers'][] = $m[1];
            }
        }

        if (preg_match_all("#(\d{4,})#", $text, $m)) {
            if (is_array($m[0])) {
                $it['TicketNumbers'] = $m[1];
            } else {
                $it['TicketNumbers'][] = $m[1];
            }
        }

        if (preg_match("#(\d{4}\.\s\d+\.\s\d+)\s+HawaiianMiles:#", $this->pdfText, $m)) {
            $it['ReservationDate'] = strtotime(str_replace(' ', '', $m[1]));
            $this->date = $it['ReservationDate'];
        }
        $year = date("Y", $this->date);

        //		$it['BaseFare'] = $this->pdf->FindSingleNode("//p[contains(text(),'Fare:')]/following::p[2]");
        //		$it['TotalCharge'] = $this->pdf->FindSingleNode("//p[contains(text(),'Total:')]/following::p[2]");
        //		$it['Currency'] = $this->pdf->FindSingleNode("//p[contains(text(),'Total:')]/following::p[1]");

        $xpath = "//p[contains(text(),'From:')]";
        $roots = $this->pdf->XPath->query($xpath);

        foreach ($roots as $root) {
            $seg = [];
            $dateFly = $this->pdf->FindSingleNode("./preceding::p[1]", $root);
            $node = $this->pdf->FindSingleNode("./following-sibling::p[1]", $root);

            if (preg_match("#(.+?)\s*\(([A-Z]{3})\)#", $node, $m)) {
                $seg['DepCode'] = $m[2];
                $seg['DepName'] = $m[1];
            }
            $node = $this->pdf->FindSingleNode("./following-sibling::p[contains(.,'To:')][1]/following::p[1]", $root);

            if (preg_match("#(.+?)\s*\(([A-Z]{3})\)#", $node, $m)) {
                $seg['ArrCode'] = $m[2];
                $seg['ArrName'] = $m[1];
            }
            $time = $this->pdf->FindSingleNode("./following-sibling::p[contains(.,'Depart:')][1]/following::p[1]", $root, true, "#\d+:\d+\s*[ap]m#i");
            $seg['DepDate'] = strtotime($this->normalize($dateFly . ' ' . $time));
            $time = $this->pdf->FindSingleNode("./following-sibling::p[contains(.,'Arrive:')][1]/following::p[1]", $root, true, "#\d+:\d+\s*[ap]m#i");
            $seg['ArrDate'] = strtotime($this->normalize($dateFly . ' ' . $time));
            $node = $this->pdf->FindSingleNode("./following-sibling::p[contains(.,'Terminal:')][1]/following::p[1]", $root);

            if (stripos($node, 'Terminal:') === false) {
                $seg['DepartureTerminal'] = $node;
                $node = $this->pdf->FindSingleNode("./following-sibling::p[contains(.,'Terminal:')][1]/following::p[3]", $root);

                if (stripos($node, 'Flight:') === false) {
                    $seg['ArrivalTerminal'] = $node;
                }
            } else {
                $node = $this->pdf->FindSingleNode("./following-sibling::p[contains(.,'Terminal:')][1]/following::p[2]", $root);

                if (stripos($node, 'Flight:') === false) {
                    $seg['ArrivalTerminal'] = $node;
                }
            }
            $node = $this->pdf->FindSingleNode("./following-sibling::p[contains(.,'Flight:')][1]/following::p[1]", $root);

            if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $seg['Duration'] = $this->pdf->FindSingleNode("./following-sibling::p[contains(.,'Duration:')][1]/following::p[1]", $root);
            $seg['Meal'] = $this->pdf->FindSingleNode("./following-sibling::p[contains(.,'Meals:')][1]/following::p[1]", $root);
            $seg['Cabin'] = $this->pdf->FindSingleNode("./following-sibling::p[contains(.,'Cabin:')][1]/following::p[1]", $root);
            $seg['Aircraft'] = $this->pdf->FindSingleNode("./following-sibling::p[contains(.,'Aircraft:')][1]/following::p[1]", $root);
            $this->pdfText = str_replace("­", " ", $this->pdfText); //html entity &shy; replace
            $text = $this->findСutSection($this->pdfText, $seg['DepCode'] . ' ' . $seg['ArrCode'], 'Itinerary');

            if (preg_match_all("#\s((?:\d[\d\w]{1,2}|N\/A))\s#s", $text, $m)) {
                $seg['Seats'] = implode(',', array_unique($m[1]));
            }
            $seg = array_filter($seg);
            $it['TripSegments'][] = $seg;
        }
        $it = array_filter($it);

        return $it;
    }

    private function parseEmailReceipt()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];

        $it['RecordLocator'] = $this->pdf->FindSingleNode("//p[contains(text(),'Confirmation Code')]/following::p[1]");

        $it['Passengers'][] = $this->pdf->FindSingleNode("//p[contains(text(),'Passenger:')]/following::p[1]");
        $it['TicketNumbers'][] = $this->pdf->FindSingleNode("//p[contains(text(),'Ticket Number:')]/following::p[1]");

        $it['ReservationDate'] = strtotime($this->pdf->FindSingleNode("//p[contains(text(),'Date Issued:')]/following::p[1]"));

        $it['BaseFare'] = $this->pdf->FindSingleNode("//p[contains(text(),'Fare:')]/following::p[2]");
        $it['TotalCharge'] = $this->pdf->FindSingleNode("//p[contains(text(),'Total:')]/following::p[2]");
        $it['Currency'] = $this->pdf->FindSingleNode("//p[contains(text(),'Total:')]/following::p[1]");

        $xpath = "//p[contains(text(),'From:')]";
        $roots = $this->pdf->XPath->query($xpath);

        foreach ($roots as $root) {
            $seg = [];
            $node = $this->pdf->FindSingleNode("./following-sibling::p[1]", $root);

            if (preg_match("#(.+?)\s*\(([A-Z]{3})\)#", $node, $m)) {
                $seg['DepCode'] = $m[2];
                $seg['DepName'] = $m[1];
            }

            if (stripos($node, 'To:') !== false) {
                $node = $this->pdf->FindSingleNode("./following::p[2]", $root);
            } else {
                $node = $this->pdf->FindSingleNode("./following-sibling::p[contains(.,'To:')][1]/following::p[1]", $root);
            }

            if (preg_match("#(.+?)\s*\(([A-Z]{3})\)#", $node, $m)) {
                $seg['ArrCode'] = $m[2];
                $seg['ArrName'] = $m[1];
            }
            $time = $this->pdf->FindSingleNode("./following-sibling::p[contains(.,'Depart:')][1]/following::p[1]", $root);
            $seg['DepDate'] = strtotime($this->normalize($time));
            $time = $this->pdf->FindSingleNode("./following-sibling::p[contains(.,'Arrive:')][1]/following::p[1]", $root);
            $seg['ArrDate'] = strtotime($this->normalize($time));
            $node = $this->pdf->FindSingleNode("./following-sibling::p[contains(.,'Terminal:')][1]/following::p[1]", $root);

            if (stripos($node, 'Terminal:') === false) {
                $seg['DepartureTerminal'] = $node;
                $node = $this->pdf->FindSingleNode("./following-sibling::p[contains(.,'Terminal:')][1]/following::p[3]", $root);

                if (stripos($node, 'Flight:') === false) {
                    $seg['ArrivalTerminal'] = $node;
                }
            } else {
                $node = $this->pdf->FindSingleNode("./following-sibling::p[contains(.,'Terminal:')][1]/following::p[2]", $root);

                if (stripos($node, 'Flight:') === false) {
                    $seg['ArrivalTerminal'] = $node;
                }
            }
            $node = $this->pdf->FindSingleNode("./following-sibling::p[contains(.,'Flight:')][1]/following::p[1]", $root);

            if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $seg['Duration'] = $this->pdf->FindSingleNode("./following-sibling::p[contains(.,'Duration:')][1]/following::p[1]", $root);
            $seg['Meal'] = $this->pdf->FindSingleNode("./following-sibling::p[contains(.,'Meals:')][1]/following::p[1]", $root);
            $seg['Cabin'] = $this->pdf->FindSingleNode("./following-sibling::p[contains(.,'Cabin:')][1]/following::p[1]", $root);
            $seg['Aircraft'] = $this->pdf->FindSingleNode("./following-sibling::p[contains(.,'Aircraft:')][1]/following::p[1]", $root);

            $seg = array_filter($seg);
            $it['TripSegments'][] = $seg;
        }
        $it = array_filter($it);

        return $it;
    }

    private function normalize($str)
    {
        $in = [
            "#^\w+,\s+(\w+)\s+(\d+),\s+(\d{4}),?\s+(\d+:\d+.+)$#",
        ];
        $out = [
            "$2 $1 $3, $4",
        ];

        return $this->dateStringToEnglish(preg_replace($in, $out, $str));
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
        $NBSP = chr(194) . chr(160);
        $body = str_replace($NBSP, ' ', html_entity_decode($body));

        foreach (self::$dict as $lang => $reBody) {
            if (stripos($body, $reBody['BODY']) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        return true;
    }
}
