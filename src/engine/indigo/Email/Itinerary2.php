<?php

namespace AwardWallet\Engine\indigo\Email;

class Itinerary2 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "indigo/it-4900411.eml, indigo/it-4951474.eml, indigo/it-5500412.eml, indigo/it-6847357.eml, indigo/it-7183872.eml, indigo/it-8663329.eml, indigo/it-8715834.eml, indigo/it-8761555.eml";

    public $reBody = [
        'en' => ['Business Park, Gurgaon, Haryana, India', 'Must Read'],
    ];
    public $reSubject = [
        'Your IndiGo Itinerary',
        'Your IndiGo Boarding Pass & Itinerary',
    ];
    public $lang = 'en';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        $its = $this->parseEmail();

        if (!$its) {
            $text = implode("\n", $parser->getRawBody());

            if (preg_match_all("#(<html>[\s\S]+<\/html>)#iU", $text, $m)) {
                $this->http->SetBody(quoted_printable_decode(implode('', $m[1])));
                $its = $this->parseEmail();
            }
        }

        if (!$its) {
            $texts = implode("\n", $parser->getRawBody());
            $posBegin1 = stripos($texts, "Content-Type: text/html");
            $i = 0;

            while ($posBegin1 !== false && $i < 30) {
                $posBegin = stripos($texts, "\n\n", $posBegin1) + 2;
                $posEnd = stripos($texts, "\n\n", $posBegin);

                if (preg_match("#filename=.*\.htm.*(?:\n.*){1,7}base64#s", substr($texts, $posBegin1, $posBegin - $posBegin1))) {
                    $t = substr($texts, $posBegin, $posEnd - $posBegin);
                    $text .= base64_decode($t);
                }
                $posBegin1 = stripos($texts, "Content-Type: text/html", $posBegin);
                $i++;
            }
            $this->http->SetEmailBody($text);
            $its = $this->parseEmail();
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "Itinerary2",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'goindigo.in')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
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
        return stripos($from, "goindigo.in") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function getPosHeaderTd($path)
    {
        return $this->http->XPath->query($path)->length + 1;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];

        $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(),'" . $this->t('Booking Reference') . "')]/following-sibling::*[normalize-space(.)]");
        $it['Status'] = $this->http->FindSingleNode("//*[text()='" . $this->t('Status') . "']/following-sibling::*[normalize-space(.)]");
        $it['ReservationDate'] = strtotime($this->http->FindSingleNode("//*[contains(text(),'" . $this->t('Date of Booking') . "')]/following-sibling::*[normalize-space(.)]", null, true, "#(.+?\d+:\d+):\d+\s*\(#"));
        $it['Passengers'] = $this->http->FindNodes("//*[contains(text(),'" . $this->t('IndiGo Passenger(s)') . "')]/ancestor::tr[1]/following-sibling::tr[1]//td[not(.//td)]", null, '/\d+\s*\.\s*(.+)/');

        if (empty($it['Passengers'])) {
            $it['Passengers'] = $this->http->FindNodes("//*[contains(text(),'" . $this->t('IndiGo Passenger(s)') . "')]/ancestor::table[1]/following::table[not(contains(.,'" . $this->t('IndiGo Flight(s)') . "'))][1]//td[not(.//td) and normalize-space(.)]", null, '/\d+\s*\.\s*(.+)/');
        }

        if (empty($it['RecordLocator'])) {
            $pos = $this->getPosHeaderTd("//text()[contains(.,'" . $this->t('Booking Reference') . "')]/ancestor::td[1]/preceding-sibling::td");
            $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(.,'" . $this->t('Booking Reference') . "')]/ancestor::tr[1]/following-sibling::tr[1]/td[$pos]");
        }

        if (empty($it['Status'])) {
            $pos = $this->getPosHeaderTd("//text()[. = '" . $this->t('Status') . "']/ancestor::td[1]/preceding-sibling::td");
            $it['Status'] = $this->http->FindSingleNode("//text()[contains(.,'" . $this->t('Status') . "')]/ancestor::tr[1]/following-sibling::tr[1]/td[$pos]");
        }

        if (empty($it['ReservationDate'])) {
            $pos = $this->getPosHeaderTd("//text()[contains(.,'" . $this->t('Date of Booking') . "')]/ancestor::td[1]/preceding-sibling::td");
            $it['ReservationDate'] = strtotime($this->http->FindSingleNode("//text()[contains(.,'" . $this->t('Date of Booking') . "')]/ancestor::tr[1]/following-sibling::tr[1]/td[$pos]", null, true, "#(.+\d+:\d+):\d+#"));
        }

        $node = $this->http->FindSingleNode("//*[contains(text(),'" . $this->t('Airfare Charges') . "')]/ancestor::tr[1]/td[3]");

        if ($node !== null) {
            $it['BaseFare'] = $this->cost($node);
        }
        $node = $this->http->FindSingleNode("//*[contains(text(),'" . $this->t('Government Service Tax') . "')]/ancestor::tr[1]/td[3]");

        if ($node !== null) {
            $it['Tax'] = $this->cost($node);
        }
        $node = $this->http->FindSingleNode("//*[contains(text(),'" . $this->t('Total Fare') . "')]/ancestor::tr[1]/td[3]");
        $nodecur = $this->http->FindSingleNode("//*[contains(text(),'" . $this->t('Total Fare') . "')]/ancestor::tr[1]/td[2]");

        if ($node !== null) {
            $it['TotalCharge'] = $this->cost($node);
            $it['Currency'] = currency($nodecur);
        }

        $seatRoute = $this->http->FindNodes("//*[text() = '" . $this->t('Services') . "']/ancestor::tr[1]/following-sibling::tr[1]/td");
        $seatRows = $this->http->FindNodes("//*[text() = '" . $this->t('Services') . "']/ancestor::tr[1]/following-sibling::tr[2]/td");

        foreach ($seatRows as $key => $value) {
            if (preg_match_all("#\bSeat\s+(\d+[A-Z])\b#", $value, $m)) {
                $seatValue[$key] = $m[1];
            } else {
                $seatValue[$key] = [];
            }
        }

        if (isset($seatValue) && !empty(array_filter($seatValue))) {
            $seat = [];

            foreach ($seatRoute as $key => $value) {
                $s = [];

                if (preg_match("#^\s*(.+)-(.+)\s*$#", $value, $m)) {
                    $s['Dep'] = trim($m[1]);
                    $s['Arr'] = trim($m[2]);
                    $s['seat'] = $seatValue[$key];
                    $seat[] = $s;
                }
            }
        }

        $getHeader = "//*[contains(text(),'" . $this->t('Departs') . "')]/ancestor::tr[1][contains(.,'" . $this->t('Arrives') . "')]";
        $xpath = $getHeader . "/following-sibling::tr";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length == 0) {
            return null;
        }

        foreach ($roots as $i => $root) {
            $seg = [];
            $dateFly = strtotime($this->http->FindSingleNode("./td[1]", $root));

            $pos = $this->getPos($getHeader, 'Departs');
            $node = $this->http->FindSingleNode("./td[{$pos}]", $root);

            if (($node !== null) && preg_match("#(\d+:\d+)#", $node, $m)) {
                $seg['DepDate'] = strtotime($m[1], $dateFly);
            }
            $pos = $this->getPos($getHeader, 'Arrives');
            $node = $this->http->FindSingleNode("./td[{$pos}]", $root);

            if (($node !== null) && preg_match("#(\d+:\d+)#", $node, $m)) {
                $seg['ArrDate'] = strtotime($m[1], $dateFly);
            }
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            $pos = $this->getPos($getHeader, 'From');
            $seg['DepName'] = $this->http->FindSingleNode("./td[{$pos}]", $root);
            $pos = $this->getPos($getHeader, 'To');
            $seg['ArrName'] = $this->http->FindSingleNode("./td[{$pos}]", $root);

            $pos = $this->getPos($getHeader, 'Dep Terminal');
            $seg['DepartureTerminal'] = $this->http->FindSingleNode("./td[{$pos}]", $root);

            $pos = $this->getPos($getHeader, 'Flight');
            $node = $this->http->FindSingleNode("./td[{$pos}]", $root);

            if (($node !== null) && preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            if (isset($seat)) {
                foreach ($seat as $key => $value) {
                    if ($value["Dep"] == $seg['DepName'] && $value["Arr"] == $seg['ArrName']) {
                        $seg['Seats'] = $value['seat'];
                    }
                }
            }
            $seg = array_filter($seg);
            $it['TripSegments'][] = $seg;
        }

        $it = array_filter($it);

        return [$it];
    }

    private function getPos($HeaderPath, $fieldName)
    {
        $xpath_pos = $HeaderPath . "/td[contains(.,'{$fieldName}')]";

        if ($this->http->XPath->query($xpath_pos)->length > 0) {
            $xpath_pos .= "/preceding-sibling::td";
        } else {
            return 0;
        }
        $pos = $this->http->XPath->query($xpath_pos)->length;

        return $pos + 1;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(text()),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(text()),'{$reBody[1]}')]")->length > 0
                ) {
                    //					if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
